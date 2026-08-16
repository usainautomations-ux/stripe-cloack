<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        No referrer leaves this page. Stripe.js and the payment iframe would
        otherwise receive the full checkout URL; more importantly, this stops
        any onward navigation from carrying our URL structure outward.
    --}}
    <meta name="referrer" content="no-referrer">

    {{-- The platform's name, never the originating website's. --}}
    <title>Secure checkout — {{ $merchantName }}</title>

    <script src="https://js.stripe.com/v3/"></script>

    <style>
        :root {
            --ink: #16181d;
            --muted: #6b7280;
            --line: #e5e7eb;
            --bg: #f7f7f8;
            --accent: #3d5afe;
            --danger: #b3261e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2rem 1rem;
            background: var(--bg);
            color: var(--ink);
            font: 400 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 2rem;
        }

        .amount {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }

        .merchant { color: var(--muted); font-size: 0.875rem; margin-top: 0.25rem; }

        hr { border: 0; border-top: 1px solid var(--line); margin: 1.5rem 0; }

        button {
            width: 100%;
            margin-top: 1.5rem;
            padding: 0.875rem;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: var(--accent);
            border: 0;
            border-radius: 8px;
            cursor: pointer;
        }

        button:disabled { opacity: 0.6; cursor: not-allowed; }

        #error {
            margin-top: 1rem;
            color: var(--danger);
            font-size: 0.875rem;
            min-height: 1.25rem;
        }

        .secured {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="amount">{{ $order->amountForHumans() }}</div>
        <div class="merchant">Payment to {{ $merchantName }}</div>

        <hr>

        <form id="payment-form">
            <div id="payment-element"></div>
            <button id="submit" type="submit">Pay {{ $order->amountForHumans() }}</button>
            <div id="error" role="alert" aria-live="polite"></div>
        </form>

        <p class="secured">Payments are processed securely by {{ $merchantName }}.</p>
    </main>

    <script>
        const stripe = Stripe(@json($publishableKey));
        const elements = stripe.elements({ clientSecret: @json($clientSecret) });

        elements.create('payment').mount('#payment-element');

        const form = document.getElementById('payment-form');
        const button = document.getElementById('submit');
        const errorBox = document.getElementById('error');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            button.disabled = true;
            errorBox.textContent = '';

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    // Returns to the platform, not to the originating site. The
                    // platform forwards the shopper onward afterwards, so the
                    // client domain never appears in a Stripe redirect.
                    return_url: @json(route('checkout.complete', $order->id)),
                },
            });

            if (error) {
                // Stripe only returns here on immediate validation failures;
                // otherwise the browser has already navigated away.
                errorBox.textContent = error.message;
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
