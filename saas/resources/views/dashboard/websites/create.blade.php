@extends('layouts.dashboard')

@section('title', 'Connect a website')

@section('content')
    <h1>Connect a website</h1>

    <div class="card">
        <p class="help" style="margin-top:0;">
            This generates a one-time setup token. Install the <strong>Usman Pay</strong>
            plugin on the WordPress site, paste the token into its settings screen,
            and the platform will verify the site controls that domain before it can
            accept payments.
        </p>

        <form method="POST" action="{{ route('dashboard.websites.store') }}">
            @csrf

            <label for="intended_domain">Domain (optional)</label>
            <input type="text" id="intended_domain" name="intended_domain" value="{{ old('intended_domain') }}" placeholder="clientwebsite.com">
            <div class="help">If set, the token can only be redeemed by a plugin installed on this exact domain.</div>

            <button type="submit">Generate setup token</button>
        </form>
    </div>
@endsection
