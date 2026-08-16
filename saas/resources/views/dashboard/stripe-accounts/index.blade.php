@extends('layouts.dashboard')

@section('title', 'Stripe accounts')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin-bottom:0;">Stripe accounts</h1>
        @if (auth()->user()->hasPermission('stripe.create'))
            <a href="{{ route('dashboard.stripe-accounts.create') }}" class="btn">Add Stripe account</a>
        @endif
    </div>
    <div style="height:1.5rem;"></div>

    @if ($accounts->isEmpty())
        <div class="empty">No Stripe accounts added yet.</div>
    @else
        <table>
            <thead><tr><th>Label</th><th>Mode</th><th>Key</th><th>Assigned users</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td><a href="{{ route('dashboard.stripe-accounts.show', $account->id) }}">{{ $account->label }}</a></td>
                        <td>{{ $account->mode }}</td>
                        <td><code>{{ $account->maskedSecretKey() }}</code></td>
                        <td>{{ $account->assignments_count }}</td>
                        <td><span class="pill pill-{{ $account->status }}">{{ $account->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
