@extends('layouts.dashboard')

@section('title', 'Overview')

@section('content')
    <h1>Overview</h1>

    <div class="grid">
        <div class="stat">
            <div class="value">{{ $websiteCount }}</div>
            <div class="label">Websites</div>
        </div>
        <div class="stat">
            <div class="value">{{ $verifiedWebsiteCount }}</div>
            <div class="label">Verified &amp; live</div>
        </div>
        <div class="stat">
            <div class="value">{{ $stripeAccountCount }}</div>
            <div class="label">Stripe accounts</div>
        </div>
        <div class="stat">
            <div class="value">{{ number_format($paidLast30DaysMinor / 100, 2) }}</div>
            <div class="label">Collected, last 30 days</div>
        </div>
    </div>

    <h2>Recent transactions</h2>

    @if ($recentOrders->isEmpty())
        <div class="empty">No transactions yet. Connect a website to get started.</div>
    @else
        <table>
            <thead>
                <tr><th>Order</th><th>Website</th><th>Stripe account</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('dashboard.orders.show', $order->id) }}">{{ $order->order_ref }}</a></td>
                        <td>{{ $order->website?->domain ?? '—' }}</td>
                        <td>{{ $order->stripeAccount?->label ?? '—' }}</td>
                        <td class="amount">{{ $order->amountForHumans() }}</td>
                        <td><span class="pill pill-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span></td>
                        <td>{{ $order->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
