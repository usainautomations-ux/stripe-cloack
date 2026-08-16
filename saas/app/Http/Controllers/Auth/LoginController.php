<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Authentication has to run before any tenant is known — the user being
     * looked up IS how we discover which tenant they belong to. This is one
     * of the few paths in the app allowed to query users cross-tenant, and it
     * does so narrowly, through withoutScope(), rather than by disabling RLS.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Brute-force protection, keyed on email+IP so an attacker cannot
        // lock a legitimate user out just by guessing their address.
        $throttleKey = 'login:' . strtolower($credentials['email']) . '|' . $request->ip();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Try again in a minute.'),
            ]);
        }

        $user = $this->context->withoutScope(
            fn () => \App\Models\User::where('email', $credentials['email'])->first()
        );

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => __('This account has been suspended.'),
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        // The 'tenant' middleware never runs on this route — it is only
        // applied to the dashboard group, and login has to work before a
        // dashboard session exists. Without binding context here, the update
        // below silently affects zero rows (Postgres RLS filters an UPDATE's
        // target rows rather than erroring), and the audit insert after it
        // fails loudly instead, since RLS rejects an INSERT outright.
        $this->bindContextFor($user);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->audit->record('user.login', $user, ['ip' => $request->ip()]);

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->bindContextFor($request->user());

        $this->audit->record('user.logout', $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function bindContextFor(\App\Models\User $user): void
    {
        if ($user->isPlatformAdmin()) {
            $this->context->elevate();
        } else {
            $this->context->setTenant($user->tenant_id);
        }
    }
}
