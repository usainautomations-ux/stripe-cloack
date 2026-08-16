@extends('layouts.dashboard')

@section('title', $website->domain)

@section('content')
    <h1>{{ $website->domain }}</h1>

    <div class="grid" style="grid-template-columns:1fr 1fr;">
        <div class="card" style="max-width:none;">
            <h2>Status</h2>
            <p><span class="pill pill-{{ $website->status }}">{{ $website->status }}</span></p>
            <p class="help">
                Owner: {{ $website->owner?->name }}<br>
                Plugin: {{ $website->plugin_version ?? 'unknown' }}<br>
                Last seen: {{ $website->last_seen_at?->diffForHumans() ?? 'never' }}
            </p>
        </div>

        <div class="card" style="max-width:none;">
            <h2>Stripe routing</h2>
            <p class="help" style="margin-top:0;">Every payment from this site is processed by the account selected here.</p>

            <form method="POST" action="{{ route('dashboard.websites.update', $website->id) }}">
                @csrf
                @method('PUT')

                <select name="stripe_account_id">
                    <option value="">— unassigned, cannot accept payments —</option>
                    @foreach ($assignableStripeAccounts as $account)
                        <option value="{{ $account->id }}" @selected($website->stripe_account_id === $account->id)>
                            {{ $account->label }} ({{ $account->mode }})
                        </option>
                    @endforeach
                </select>
                @if ($assignableStripeAccounts->isEmpty())
                    <div class="help">You have no Stripe accounts assigned to you. Ask an administrator to grant one.</div>
                @endif

                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <h2>Recent orders</h2>
    @if ($recentOrders->isEmpty())
        <div class="empty">No orders from this site yet.</div>
    @else
        <table>
            <thead><tr><th>Order</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('dashboard.orders.show', $order->id) }}">{{ $order->order_ref }}</a></td>
                        <td class="amount">{{ $order->amountForHumans() }}</td>
                        <td><span class="pill pill-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span></td>
                        <td>{{ $order->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
