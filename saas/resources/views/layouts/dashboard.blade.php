<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — {{ config('app.platform_name', 'usman.com') }}</title>
    <style>
        :root {
            --ink: #16181d; --muted: #6b7280; --line: #e5e7eb; --bg: #f7f7f8;
            --surface: #fff; --accent: #3d5afe; --danger: #b3261e; --success: #1a7f37;
            --warn: #a15c00;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font: 400 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem; height: 56px; background: var(--surface); border-bottom: 1px solid var(--line);
        }
        .topbar .brand { font-weight: 700; letter-spacing: -0.01em; }
        .topbar nav { display: flex; gap: 1.25rem; font-size: 0.9rem; }
        .topbar nav a { color: var(--ink); }
        .topbar .right { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; color: var(--muted); }
        .topbar form { display: inline; }
        .topbar button.link {
            background: none; border: 0; color: var(--muted); cursor: pointer; font: inherit; padding: 0;
        }

        main { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

        h1 { font-size: 1.375rem; margin: 0 0 1.5rem; letter-spacing: -0.01em; }
        h2 { font-size: 1.05rem; margin: 0 0 0.75rem; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 1.25rem; }
        .stat .value { font-size: 1.75rem; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
        .stat .label { color: var(--muted); font-size: 0.8rem; margin-top: 0.25rem; }

        table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        th, td { text-align: left; padding: 0.7rem 1rem; border-bottom: 1px solid var(--line); font-size: 0.875rem; }
        th { color: var(--muted); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; background: #fafafa; }
        tr:last-child td { border-bottom: 0; }
        .amount { font-variant-numeric: tabular-nums; }

        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 1.5rem; max-width: 480px; }

        label { display: block; font-size: 0.8rem; font-weight: 600; margin: 1rem 0 0.35rem; }
        label:first-child { margin-top: 0; }
        input[type=text], input[type=email], input[type=password], select {
            width: 100%; padding: 0.55rem 0.7rem; border: 1px solid var(--line); border-radius: 6px; font: inherit;
        }
        .help { color: var(--muted); font-size: 0.8rem; margin-top: 0.3rem; }

        button, .btn {
            display: inline-block; margin-top: 1.25rem; padding: 0.6rem 1.1rem; font-size: 0.9rem; font-weight: 600;
            color: #fff; background: var(--accent); border: 0; border-radius: 7px; cursor: pointer; text-decoration: none;
        }
        .btn-secondary { background: var(--surface); color: var(--ink); border: 1px solid var(--line); }

        .pill { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .pill-verified, .pill-paid, .pill-active, .pill-succeeded { background: #e6f4ea; color: var(--success); }
        .pill-pending, .pill-processing { background: #fff4e0; color: var(--warn); }
        .pill-failed, .pill-disputed, .pill-suspended, .pill-revoked { background: #fce8e6; color: var(--danger); }
        .pill-refunded, .pill-partially_refunded, .pill-cancelled { background: #eee; color: var(--muted); }

        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .alert-success { background: #e6f4ea; color: var(--success); }
        .alert-error { background: #fce8e6; color: var(--danger); }

        .token-box {
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace; background: #16181d; color: #e6eaee;
            padding: 1rem; border-radius: 8px; word-break: break-all; font-size: 0.85rem; margin-bottom: 0.5rem;
        }

        .empty { color: var(--muted); padding: 2rem; text-align: center; }
    </style>
</head>
<body>
    @auth
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:2rem;">
                <span class="brand">{{ config('app.platform_name', 'usman.com') }}</span>
                <nav>
                    <a href="{{ route('dashboard.index') }}">Overview</a>
                    <a href="{{ route('dashboard.websites.index') }}">Websites</a>
                    @if (auth()->user()->hasPermission('stripe.view'))
                        <a href="{{ route('dashboard.stripe-accounts.index') }}">Stripe accounts</a>
                    @endif
                    <a href="{{ route('dashboard.orders.index') }}">Transactions</a>
                </nav>
            </div>
            <div class="right">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link">Log out</button>
                </form>
            </div>
        </div>
    @endauth

    <main>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
