<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StripeAccount;
use App\Models\StripeAccountAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Stripe\StripeClientFactory;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use SensitiveParameter;

class StripeAccountController extends Controller
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize($request, 'stripe.view');

        return view('dashboard.stripe-accounts.index', [
            'accounts' => StripeAccount::withCount('assignments')->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize($request, 'stripe.create');

        return view('dashboard.stripe-accounts.create');
    }

    /**
     * The secret key is validated against Stripe's own API before it is ever
     * saved, so a typo or a revoked key is caught here rather than at a
     * customer's checkout. It is encrypted the moment validation succeeds and
     * is never written to a log, a session, or a response body from this
     * point forward.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request, 'stripe.create');
        $this->requireTenantUser($request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:test,live'],
            'publishable_key' => ['required', 'string', 'starts_with:pk_'],
            'secret_key' => ['required', 'string', 'starts_with:sk_'],
            'statement_descriptor' => ['nullable', 'string', 'max:22'],
        ]);

        $this->guardKeyModeMatches($validated['secret_key'], $validated['mode']);

        $check = $this->stripe->validateKey($validated['secret_key']);

        if (! $check['valid']) {
            return back()->withErrors(['secret_key' => $check['message']])->withInput();
        }

        $account = new StripeAccount([
            'label' => $validated['label'],
            'mode' => $validated['mode'],
            'status' => 'active',
            'publishable_key' => $validated['publishable_key'],
            'statement_descriptor' => $validated['statement_descriptor'] ?? null,
        ]);
        $account->setSecretKey($validated['secret_key']);
        $account->save();

        $this->audit->record('stripe_account.created', $account, [
            'label' => $account->label,
            'mode' => $account->mode,
            'stripe_account_name' => $check['account_name'],
        ]);

        return redirect()
            ->route('dashboard.stripe-accounts.index')
            ->with('status', "Stripe account \"{$account->label}\" added and verified.");
    }

    public function show(Request $request, string $stripeAccount): View
    {
        $this->authorize($request, 'stripe.view');

        $account = $this->findAccount($stripeAccount);

        $assignableUsers = $this->context->withoutScope(
            fn () => User::where('tenant_id', $account->tenant_id)
                ->orderBy('name')
                ->get()
        );

        return view('dashboard.stripe-accounts.show', [
            'account' => $account,
            'assignedUserIds' => $account->users()->pluck('users.id')->all(),
            'assignableUsers' => $assignableUsers,
        ]);
    }

    /**
     * Grants or revokes this Stripe account for one user. This table is the
     * actual authorization boundary for money movement, so every change here
     * is audited individually rather than as a bulk "sync".
     */
    public function toggleAssignment(Request $request, string $stripeAccount): RedirectResponse
    {
        $this->authorize($request, 'stripe.assign');

        $account = $this->findAccount($stripeAccount);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid'],
            'action' => ['required', 'in:grant,revoke'],
        ]);

        if ($validated['action'] === 'grant') {
            StripeAccountAssignment::firstOrCreate(
                ['stripe_account_id' => $account->id, 'user_id' => $validated['user_id']],
                ['tenant_id' => $account->tenant_id, 'granted_by_user_id' => $request->user()->id]
            );

            $this->audit->record('stripe_account.assigned', $account, ['user_id' => $validated['user_id']]);
        } else {
            StripeAccountAssignment::where('stripe_account_id', $account->id)
                ->where('user_id', $validated['user_id'])
                ->delete();

            $this->audit->record('stripe_account.unassigned', $account, ['user_id' => $validated['user_id']]);
        }

        return back()->with('status', 'Assignment updated.');
    }

    /**
     * Deliberately no route to view or export the decrypted key. Rotation
     * replaces it outright — there is no "edit" that exposes the old value.
     */
    public function rotate(Request $request, string $stripeAccount): RedirectResponse
    {
        $this->authorize($request, 'stripe.update');

        $account = $this->findAccount($stripeAccount);

        $validated = $request->validate([
            'secret_key' => ['required', 'string', 'starts_with:sk_'],
        ]);

        $this->guardKeyModeMatches($validated['secret_key'], $account->mode);

        $check = $this->stripe->validateKey($validated['secret_key']);

        if (! $check['valid']) {
            return back()->withErrors(['secret_key' => $check['message']]);
        }

        $account->setSecretKey($validated['secret_key']);
        $account->save();

        $this->audit->record('stripe_account.key_rotated', $account);

        return back()->with('status', 'Secret key rotated.');
    }

    private function guardKeyModeMatches(#[SensitiveParameter] string $secretKey, string $mode): void
    {
        // sk_test_ paired with mode=live (or the reverse) is the classic way an
        // operator accidentally puts a test key in front of real customers, or
        // a live key in front of a staging site. Stripe's own validation would
        // not catch this — both key types are individually valid.
        $expectedPrefix = $mode === 'live' ? 'sk_live_' : 'sk_test_';

        abort_if(
            ! str_starts_with($secretKey, $expectedPrefix),
            422,
            "This looks like a " . ($mode === 'live' ? 'test' : 'live') . " key, but the account is set to {$mode} mode."
        );
    }

    private function findAccount(string $id): StripeAccount
    {
        $account = StripeAccount::where('id', $id)->first();

        abort_unless($account, 404);

        return $account;
    }

    private function authorize(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }

    /**
     * A platform administrator has tenant_id === null by design. Every Stripe
     * account belongs to exactly one tenant (the column is NOT NULL), so
     * without this check the request would reach the database and fail there
     * as a raw constraint violation instead of an explanation.
     */
    private function requireTenantUser(Request $request): void
    {
        abort_if(
            $request->user()->tenant_id === null,
            403,
            'Platform administrators manage tenants, not Stripe accounts. Log in as a user within a tenant to add one.'
        );
    }
}
