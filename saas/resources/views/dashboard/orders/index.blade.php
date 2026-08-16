@extends('layouts.dashboard')

@section('title', 'Transactions')

@section('content')
    <h1>Transactions</h1>

    <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1.5rem;">
        <div>
            <label for="status">Status</label>
            <select id="status" name="status" style="width:auto;">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ str_replace('_', ' ', $status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="website_id">Website</label>
            <select id="website_id" name="website_id" style="width:auto;">
                <option value="">All</option>
                @foreach ($websites as $site)
                    <option value="{{ $site->id }}" @selected(($filters['website_id'] ?? null) === $site->id)>{{ $site->domain }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="stripe_account_id">Stripe account</label>
            <select id="stripe_account_id" name="stripe_account_id" style="width:auto;">
                <option value="">All</option>
                @foreach ($stripeAccounts as $account)
                    <option value="{{ $account->id }}" @selected(($filters['stripe_account_id'] ?? null) === $account->id)>{{ $account->label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from">From</label>
            <input type="text" id="from" name="from" value="{{ $filters['from'] ?? '' }}" placeholder="YYYY-MM-DD" style="width:140px;">
        </div>
        <div>
            <label for="to">To</label>
            <input type="text" id="to" name="to" value="{{ $filters['to'] ?? '' }}" placeholder="YYYY-MM-DD" style="width:140px;">
        </div>
        <button type="submit" style="margin-top:0;">Filter</button>
    </form>

    @if ($orders->isEmpty())
        <div class="empty">No transactions match these filters.</div>
    @else
        <table>
            <thead>
                <tr><th>Order</th><th>Website</th><th>Stripe account</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
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
        <div style="margin-top:1rem;">{{ $orders->links() }}</div>
    @endif
@endsection
