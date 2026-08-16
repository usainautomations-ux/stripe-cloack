<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize($request, 'users.view');

        return view('dashboard.users.index', [
            'users' => User::with('role')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize($request, 'users.create');
        $this->requireTenantUser($request);

        return view('dashboard.users.create', [
            'roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * There is no invitation email — nothing in this codebase sends mail yet.
     * The admin sets an initial password directly and is shown it once, the
     * same one-time-reveal pattern used for setup tokens, so it can be handed
     * to the new user over a channel the admin already trusts.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request, 'users.create');
        $this->requireTenantUser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_key' => ['required', 'string', Rule::in($this->assignableRoles()->pluck('key'))],
        ]);

        if (User::withoutGlobalScopes()->where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.'])->withInput();
        }

        $password = Str::password(16);

        $user = new User([
            'tenant_id' => $request->user()->tenant_id,
            'role_key' => $validated['role_key'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['status' => 'active'])->save();

        $this->audit->record('user.created', $user, [
            'email' => $user->email,
            'role_key' => $user->role_key,
        ]);

        return redirect()
            ->route('dashboard.users.index')
            ->with('generated_password', $password)
            ->with('generated_password_email', $user->email)
            ->with('status', "User \"{$user->name}\" created. Share the password shown below with them now — it will not be shown again.");
    }

    public function show(Request $request, string $user): View
    {
        $this->authorize($request, 'users.view');

        $model = $this->findUser($user);

        return view('dashboard.users.show', [
            'targetUser' => $model->load('role'),
            'stripeAccounts' => $model->stripeAccounts,
            'websites' => $model->websites,
        ]);
    }

    /**
     * Deactivates a user rather than deleting the row outright — their name
     * needs to keep showing up as the actor on past audit log entries and
     * past refunds. status='suspended' blocks login immediately.
     */
    public function deactivate(Request $request, string $user): RedirectResponse
    {
        $this->authorize($request, 'users.delete');

        $model = $this->findUser($user);

        abort_if($model->id === $request->user()->id, 422, 'You cannot deactivate your own account.');

        $model->forceFill(['status' => 'suspended'])->save();

        $this->audit->record('user.deactivated', $model);

        return back()->with('status', "User \"{$model->name}\" deactivated.");
    }

    private function findUser(string $id): User
    {
        $model = User::where('id', $id)->first();

        abort_unless($model, 404);

        return $model;
    }

    /**
     * The roles an acting user may hand out. A tenant owner should not be able
     * to create a platform administrator from inside a tenant's own dashboard
     * — that role sits above tenancy entirely.
     */
    private function assignableRoles()
    {
        return Role::where('is_platform_role', false)->orderBy('name')->get();
    }

    private function requireTenantUser(Request $request): void
    {
        abort_if(
            $request->user()->tenant_id === null,
            403,
            'Platform administrators manage tenants, not tenant users. Log in as a user within a tenant to invite one.'
        );
    }

    private function authorize(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }
}
