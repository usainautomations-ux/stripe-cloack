@extends('layouts.dashboard')

@section('title', $account->label)

@section('content')
    <h1>{{ $account->label }}</h1>
    <p class="help" style="margin-top:-1rem;">{{ $account->mode }} mode · <code>{{ $account->maskedSecretKey() }}</code></p>

    <div class="grid" style="grid-template-columns:1fr 1fr;">
        <div class="card" style="max-width:none;">
            <h2>Assigned users</h2>
            <p class="help" style="margin-top:0;">Only users granted here can route a website's payments through this account.</p>

            @foreach ($assignableUsers as $user)
                <form method="POST" action="{{ route('dashboard.stripe-accounts.assignments', $account->id) }}"
                      style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--line);">
                    @csrf
                    <span>{{ $user->name }} <span class="help">({{ $user->email }})</span></span>
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    @if (in_array($user->id, $assignedUserIds, true))
                        <input type="hidden" name="action" value="revoke">
                        <button type="submit" class="btn-secondary" style="margin:0;padding:0.3rem 0.7rem;font-size:0.8rem;">Revoke</button>
                    @else
                        <input type="hidden" name="action" value="grant">
                        <button type="submit" style="margin:0;padding:0.3rem 0.7rem;font-size:0.8rem;">Grant</button>
                    @endif
                </form>
            @endforeach
        </div>

        @if (auth()->user()->hasPermission('stripe.update'))
            <div class="card" style="max-width:none;">
                <h2>Rotate secret key</h2>
                <p class="help" style="margin-top:0;">Replaces the stored key outright. The old key cannot be recovered afterward.</p>

                <form method="POST" action="{{ route('dashboard.stripe-accounts.rotate', $account->id) }}">
                    @csrf
                    <label for="secret_key">New secret key</label>
                    <input type="text" id="secret_key" name="secret_key" placeholder="sk_{{ $account->mode }}_…" required>
                    <button type="submit">Verify and rotate</button>
                </form>
            </div>
        @endif
    </div>
@endsection
