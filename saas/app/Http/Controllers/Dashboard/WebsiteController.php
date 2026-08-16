<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SetupToken;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAny($request, ['websites.view.all', 'websites.view.own']);

        $user = $request->user();

        $websites = $user->hasPermission('websites.view.all')
            ? Website::query()
            : Website::where('owner_user_id', $user->id);

        return view('dashboard.websites.index', [
            'websites' => $websites->with('stripeAccount')->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAny($request, ['websites.create']);

        return view('dashboard.websites.create');
    }

    /**
     * There is no "create a website" form field for a domain that gets saved
     * directly — a website record is only ever created by the plugin calling
     * /v1/website/register, because that is what proves domain ownership.
     * What this action actually does is mint the setup token that starts that
     * process, and show it to the operator exactly once.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAny($request, ['websites.create']);
        $this->requireTenantUser($request);

        $validated = $request->validate([
            'intended_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $issued = SetupToken::issue($request->user(), $validated['intended_domain'] ?? null);

        return redirect()
            ->route('dashboard.websites.index')
            ->with('setup_token', $issued['token'])
            ->with('status', 'Setup token generated. Paste it into the plugin within 15 minutes — it will not be shown again.');
    }

    /**
     * Takes the website id as a plain string rather than using implicit route
     * model binding. Binding resolves the model while the route is being
     * matched, which can run before the 'tenant' middleware has bound the RLS
     * context for this request — resolving here instead guarantees the tenant
     * context is already set, because all middleware complete before a
     * controller method body runs.
     */
    public function show(Request $request, string $website): View
    {
        $model = $this->findWebsite($request, $website);

        return view('dashboard.websites.show', [
            'website' => $model->load('stripeAccount', 'owner'),
            'assignableStripeAccounts' => $request->user()->stripeAccounts()->get(),
            'recentOrders' => $model->orders()->latest()->limit(15)->get(),
        ]);
    }

    /**
     * The only field this actually changes is which Stripe account a website's
     * payments route through. Everything else about a website (its domain,
     * its credentials) is set during registration and is not editable here.
     */
    public function update(Request $request, string $website): RedirectResponse
    {
        $model = $this->findWebsite($request, $website, 'websites.assign_stripe');

        $validated = $request->validate([
            'stripe_account_id' => ['nullable', 'uuid'],
        ]);

        if ($validated['stripe_account_id'] ?? null) {
            // A user may only route a website's money through a Stripe account
            // that has actually been granted to them — this check is the whole
            // of what stops an employee from picking an account nobody gave
            // them access to.
            if (! $request->user()->canUseStripeAccount($validated['stripe_account_id'])) {
                return back()->withErrors(['stripe_account_id' => 'You are not assigned to that Stripe account.']);
            }
        }

        $model->update(['stripe_account_id' => $validated['stripe_account_id'] ?? null]);

        return back()->with('status', 'Website updated.');
    }

    private function findWebsite(Request $request, string $id, ?string $permission = null): Website
    {
        $user = $request->user();
        $canViewAll = $user->hasPermission('websites.view.all');

        $query = $canViewAll ? Website::query() : Website::where('owner_user_id', $user->id);

        $model = $query->where('id', $id)->first();

        if (! $model) {
            abort(404);
        }

        if ($permission && ! $user->hasPermission($permission)) {
            abort(403);
        }

        return $model;
    }

    private function authorizeAny(Request $request, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }

    /**
     * A platform administrator has tenant_id === null by design — they sit
     * above tenancy to manage tenants themselves, not to operate inside one.
     * Every website belongs to exactly one tenant (the column is NOT NULL),
     * so creating one requires an actor who actually has a tenant. Without
     * this check, the request reaches the database and fails there instead,
     * as a raw constraint violation rather than a message that explains what
     * went wrong.
     */
    private function requireTenantUser(Request $request): void
    {
        abort_if(
            $request->user()->tenant_id === null,
            403,
            'Platform administrators manage tenants, not websites. Log in as a user within a tenant to connect a website.'
        );
    }
}
