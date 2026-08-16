<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripeAccount;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $websites = $user->hasPermission('websites.view.all')
            ? Website::query()
            : Website::where('owner_user_id', $user->id);

        $orders = $user->hasPermission('payments.view.all')
            ? Order::query()
            : Order::whereIn('website_id', (clone $websites)->pluck('id'));

        return view('dashboard.index', [
            'websiteCount' => (clone $websites)->count(),
            'verifiedWebsiteCount' => (clone $websites)->where('status', 'verified')->count(),
            'stripeAccountCount' => StripeAccount::count(),
            'recentOrders' => (clone $orders)->with(['website', 'stripeAccount'])
                ->latest()
                ->limit(10)
                ->get(),
            'paidLast30DaysMinor' => (clone $orders)
                ->where('status', 'paid')
                ->where('created_at', '>=', now()->subDays(30))
                ->sum('amount_minor'),
        ]);
    }
}
