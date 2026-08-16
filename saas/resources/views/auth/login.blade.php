@extends('layouts.dashboard')

@section('title', 'Log in')

@section('content')
    <div style="display:flex;justify-content:center;margin-top:3rem;">
        <div class="card" style="width:100%;">
            <h1 style="margin-bottom:0.25rem;">{{ config('app.platform_name', 'usman.com') }}</h1>
            <p class="help" style="margin-bottom:1.5rem;">Sign in to your dashboard.</p>

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;margin-top:1rem;">
                    <input type="checkbox" name="remember" value="1" style="width:auto;">
                    Keep me signed in
                </label>

                <button type="submit" style="width:100%;">Sign in</button>
            </form>
        </div>
    </div>
@endsection
