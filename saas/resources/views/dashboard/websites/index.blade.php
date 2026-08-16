@extends('layouts.dashboard')

@section('title', 'Websites')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin-bottom:0;">Websites</h1>
        <a href="{{ route('dashboard.websites.create') }}" class="btn">Connect a website</a>
    </div>
    <div style="height:1.5rem;"></div>

    @if (session('setup_token'))
        <div class="alert" style="background:#fff4e0;">
            <strong>Setup token — copy this now, it will not be shown again:</strong>
            <div class="token-box">{{ session('setup_token') }}</div>
            Paste it into the plugin's <strong>Settings → Usman Pay</strong> screen within 15 minutes.
        </div>
    @endif

    @if ($websites->isEmpty())
        <div class="empty">No websites connected yet.</div>
    @else
        <table>
            <thead>
                <tr><th>Domain</th><th>Status</th><th>Stripe account</th><th>Last seen</th></tr>
            </thead>
            <tbody>
                @foreach ($websites as $website)
                    <tr>
                        <td><a href="{{ route('dashboard.websites.show', $website->id) }}">{{ $website->domain }}</a></td>
                        <td><span class="pill pill-{{ $website->status }}">{{ $website->status }}</span></td>
                        <td>{{ $website->stripeAccount?->label ?? '— unassigned —' }}</td>
                        <td>{{ $website->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:1rem;">{{ $websites->links() }}</div>
    @endif
@endsection
