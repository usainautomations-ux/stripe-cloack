@extends('layouts.dashboard')

@section('title', 'Add Stripe account')

@section('content')
    <h1>Add a Stripe account</h1>

    <div class="card">
        <p class="help" style="margin-top:0;">
            The secret key is verified against Stripe before it's saved, then
            encrypted. It is never shown again after this — rotate it if you
            need to replace it later.
        </p>

        <form method="POST" action="{{ route('dashboard.stripe-accounts.store') }}">
            @csrf

            <label for="label">Label</label>
            <input type="text" id="label" name="label" value="{{ old('label') }}" placeholder="e.g. Acme Live" required>

            <label for="mode">Mode</label>
            <select id="mode" name="mode" required>
                <option value="test" @selected(old('mode') === 'test')>Test</option>
                <option value="live" @selected(old('mode') === 'live')>Live</option>
            </select>

            <label for="publishable_key">Publishable key</label>
            <input type="text" id="publishable_key" name="publishable_key" value="{{ old('publishable_key') }}" placeholder="pk_live_…" required>

            <label for="secret_key">Secret key</label>
            <input type="text" id="secret_key" name="secret_key" placeholder="sk_live_…" required>

            <label for="statement_descriptor">Statement descriptor (optional)</label>
            <input type="text" id="statement_descriptor" name="statement_descriptor" value="{{ old('statement_descriptor') }}" maxlength="22" placeholder="USMAN.COM">
            <div class="help">Shown on the cardholder's statement. Never use a client's site name here.</div>

            <button type="submit">Verify and add</button>
        </form>
    </div>
@endsection
