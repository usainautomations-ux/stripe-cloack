@extends('layouts.dashboard')

@section('title', 'Users')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin-bottom:0;">Users</h1>
        @if (auth()->user()->hasPermission('users.create'))
            <a href="{{ route('dashboard.users.create') }}" class="btn">Invite a user</a>
        @endif
    </div>
    <div style="height:1.5rem;"></div>

    @if (session('generated_password'))
        <div class="alert" style="background:#fff4e0;">
            <strong>Password for {{ session('generated_password_email') }} — copy this now, it will not be shown again:</strong>
            <div class="token-box">{{ session('generated_password') }}</div>
            Share it with them over a channel you trust. They can change it once logged in.
        </div>
    @endif

    @if ($users->isEmpty())
        <div class="empty">No users yet.</div>
    @else
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td><a href="{{ route('dashboard.users.show', $user->id) }}">{{ $user->name }}</a></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role?->name ?? $user->role_key }}</td>
                        <td><span class="pill pill-{{ $user->status }}">{{ $user->status }}</span></td>
                        <td>{{ $user->last_login_at?->diffForHumans() ?? 'never' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:1rem;">{{ $users->links() }}</div>
    @endif
@endsection
