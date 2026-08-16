@extends('layouts.dashboard')

@section('title', 'Invite a user')

@section('content')
    <h1>Invite a user</h1>

    <div class="card">
        <p class="help" style="margin-top:0;">
            No email is sent — you'll be shown a one-time password on the next
            screen to share with them yourself. They can change it once they
            log in.
        </p>

        <form method="POST" action="{{ route('dashboard.users.store') }}">
            @csrf

            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>

            <label for="role_key">Role</label>
            <select id="role_key" name="role_key" required>
                <option value="">Choose a role…</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->key }}" @selected(old('role_key') === $role->key)>{{ $role->name }}</option>
                @endforeach
            </select>
            @if($roles->isNotEmpty())
                <div class="help">{{ $roles->firstWhere('key', old('role_key'))?->description }}</div>
            @endif

            <button type="submit">Create user</button>
        </form>
    </div>
@endsection
