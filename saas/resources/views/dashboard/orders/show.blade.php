@extends('layouts.dashboard')

@section('title', $order->order_ref)

@section('content')
    <h1>Order {{ $order->order_ref }}</h1>

    <div class="grid" style="grid-template-columns:1fr 1fr;">
        <div class="card" style="max-width:none;">
            <h2>Summary</h2>
            <table style="border:0;">
                <tr><td style="border:0;padding-left:0;">Status</td><td style="border:0;"><span class="pill pill-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span></td></tr>
                <tr><td style="border:0;padding-left:0;">Amount</td><td style="border:0;" class="amount">{{ $order->amountForHumans() }}</td></tr>
                <tr><td style="border:0;padding-left:0;">Refunded</td><td style="border:0;" class="amount">{{ number_format($order->amount_refunded_minor / 100, 2) }}</td></tr>
                <tr><td style="border:0;padding-left:0;">Website</td><td style="border:0;">{{ $order->website?->domain ?? '—' }}</td></tr>
                <tr><td style="border:0;padding-left:0;">Stripe account</td><td style="border:0;">{{ $order->stripeAccount?->label ?? '—' }}</td></tr>
                <tr><td style="border:0;padding-left:0;">Customer</td><td style="border:0;">{{ $order->customer?->email ?? '—' }}</td></tr>
                <tr><td style="border:0;padding-left:0;">Created</td><td style="border:0;">{{ $order->created_at->format('M j, Y H:i') }}</td></tr>
            </table>
        </div>

        <div class="card" style="max-width:none;">
            <h2>Transactions</h2>
            @forelse ($order->transactions as $transaction)
                <div style="padding:0.5rem 0;border-bottom:1px solid var(--line);font-size:0.875rem;">
                    <span class="pill pill-{{ $transaction->status }}">{{ $transaction->type }} / {{ $transaction->status }}</span>
                    <span class="amount" style="float:right;">{{ number_format($transaction->amount_minor / 100, 2) }} {{ strtoupper($transaction->currency) }}</span>
                    @if ($transaction->failure_message)
                        <div class="help">{{ $transaction->failure_message }}</div>
                    @endif
                </div>
            @empty
                <div class="help">No transactions recorded.</div>
            @endforelse
        </div>
    </div>

    @if ($order->refunds->isNotEmpty())
        <h2>Refunds</h2>
        <table>
            <thead><tr><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @foreach ($order->refunds as $refund)
                    <tr>
                        <td class="amount">{{ number_format($refund->amount_minor / 100, 2) }}</td>
                        <td>{{ $refund->reason ?? '—' }}</td>
                        <td><span class="pill pill-{{ $refund->status }}">{{ $refund->status }}</span></td>
                        <td>{{ $refund->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
