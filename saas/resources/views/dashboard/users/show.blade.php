@extends('layouts.dashboard')

@section('title', $targetUser->name)

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin-bottom:0;">{{ $targetUser->name }}</h1>
        @if (auth()->user()->hasPermission('users.delete') && $targetUser->id !== auth()->id() && $targetUser->status === 'active')
            <form method="POST" action="{{ route('dashboard.users.deactivate', $targetUser->id) }}"
                  onsubmit="return confirm('Deactivate {{ $targetUser->name }}? They will be signed out and unable to log in.');">
                @csrf
                <button type="submit" class="btn-secondary" style="margin:0;">Deactivate</button>
            </form>
        @endif
    </div>
    <div style="height:1.5rem;"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr;">
        <div class="card" style="max-width:none;">
            <h2>Details</h2>
            <p class="help" style="margin-top:0;">
                Email: {{ $targetUser->email }}<br>
                Role: {{ $targetUser->role?->name ?? $targetUser->role_key }}<br>
                Status: <span class="pill pill-{{ $targetUser->status }}">{{ $targetUser->status }}</span><br>
                Last login: {{ $targetUser->last_login_at?->diffForHumans() ?? 'never' }}
            </p>
        </div>

        <div class="card" style="max-width:none;">
            <h2>Stripe access</h2>
            @forelse ($stripeAccounts as $account)
                <div style="padding:0.4rem 0;border-bottom:1px solid var(--line);">
                    <a href="{{ route('dashboard.stripe-accounts.show', $account->id) }}">{{ $account->label }}</a>
                    <span class="help">({{ $account->mode }})</span>
                </div>
            @empty
                <div class="help">No Stripe accounts assigned. Grant access from a Stripe account's page.</div>
            @endforelse
        </div>
    </div>

    <h2>Websites owned</h2>
    @if ($websites->isEmpty())
        <div class="empty">No websites.</div>
    @else
        <table>
            <thead><tr><th>Domain</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($websites as $website)
                    <tr>
                        <td><a href="{{ route('dashboard.websites.show', $website->id) }}">{{ $website->domain }}</a></td>
                        <td><span class="pill pill-{{ $website->status }}">{{ $website->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
