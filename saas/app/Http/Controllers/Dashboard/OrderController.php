<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripeAccount;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('payments.view.all') || $user->hasPermission('payments.view.own'),
            403
        );

        $query = $user->hasPermission('payments.view.all')
            ? Order::query()
            : Order::whereIn('website_id', Website::where('owner_user_id', $user->id)->pluck('id'));

        $filters = $request->only(['status', 'website_id', 'stripe_account_id', 'currency', 'from', 'to']);

        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['website_id'] ?? null, fn ($q, $v) => $q->where('website_id', $v))
            ->when($filters['stripe_account_id'] ?? null, fn ($q, $v) => $q->where('stripe_account_id', $v))
            ->when($filters['currency'] ?? null, fn ($q, $v) => $q->where('currency', strtolower($v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'));

        return view('dashboard.orders.index', [
            'orders' => $query->with(['website', 'stripeAccount'])->latest()->paginate(25)->withQueryString(),
            'filters' => $filters,
            'websites' => $user->hasPermission('websites.view.all')
                ? Website::orderBy('domain')->get(['id', 'domain'])
                : Website::where('owner_user_id', $user->id)->orderBy('domain')->get(['id', 'domain']),
            'stripeAccounts' => StripeAccount::orderBy('label')->get(['id', 'label']),
            'statuses' => ['pending', 'processing', 'paid', 'failed', 'refunded', 'partially_refunded', 'disputed', 'cancelled'],
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $user = $request->user();
        $model = Order::with(['website', 'stripeAccount', 'transactions', 'refunds', 'customer'])
            ->where('id', $order)
            ->first();

        abort_unless($model, 404);

        $ownsWebsite = $model->website?->owner_user_id === $user->id;

        abort_unless($user->hasPermission('payments.view.all') || $ownsWebsite, 403);

        return view('dashboard.orders.show', ['order' => $model]);
    }
}
