<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Payment received</title>
    <style>
        body {
            margin: 0; padding: 3rem 1rem; background: #f7f7f8; color: #16181d;
            font: 400 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex; justify-content: center;
        }
        .card {
            max-width: 460px; width: 100%; background: #fff; border: 1px solid #e5e7eb;
            border-radius: 12px; padding: 2rem; text-align: center;
        }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { color: #6b7280; margin: 0.5rem 0; }
        a { display: inline-block; margin-top: 1.5rem; color: #3d5afe; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Thank you — your payment is being confirmed</h1>

        {{--
            Deliberately not "Payment complete". The redirect only means the
            shopper finished the form; confirmation arrives by webhook, and the
            originating site is told to trust that and nothing else.
        --}}
        <p>We've received your payment and are confirming it now. You'll get an
           email receipt once it settles, usually within a few seconds.</p>

        <p>Reference: {{ substr($order->id, 0, 8) }}</p>

        @if ($returnUrl)
            <a href="{{ $returnUrl }}">Return to the store</a>
        @endif
    </main>
</body>
</html>
