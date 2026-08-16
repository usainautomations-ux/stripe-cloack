<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Already paid</title>
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
        p { color: #6b7280; }
    </style>
</head>
<body>
    <main class="card">
        <h1>This order has already been paid</h1>
        <p>No further payment is needed. If you believe you've been charged
           twice, contact the store and quote reference {{ substr($order->id, 0, 8) }}.</p>
    </main>
</body>
</html>
