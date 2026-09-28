/**
 * ============================================================
 * Stripe Gateway Hub — Integration Examples
 * ============================================================
 * One SDK, any platform. All Stripe keys stay in the central hub.
 */

// ─────────────────────────────────────────────
// 1. VANILLA JAVASCRIPT (any website)
// ─────────────────────────────────────────────
/*
<script src="https://unpkg.com/@stripe-gateway-hub/sdk/dist/index.umd.js"></script>
<script>
  const gateway = new StripeGateway('sgw_your_key', 'https://payments.example.com')

  document.getElementById('buy-btn').addEventListener('click', async () => {
    try {
      const session = await gateway.createCheckout({
        priceId: 'price_abc123',
        customerEmail: document.getElementById('email').value,
        successUrl: window.location.origin + '/thank-you?order_id={ORDER_ID}',
        cancelUrl: window.location.href,
        description: 'Premium Plan Purchase',  // Visible in Stripe Dashboard
      })
      window.location.href = session.checkoutUrl
    } catch (err) {
      alert(err.message)
    }
  })
</script>
*/

// ─────────────────────────────────────────────
// 2. REACT / NEXT.JS
// ─────────────────────────────────────────────
import { StripeGateway } from '@stripe-gateway-hub/sdk'

const gateway = new StripeGateway(
  process.env.NEXT_PUBLIC_GATEWAY_API_KEY!,
  process.env.NEXT_PUBLIC_GATEWAY_URL!
)

// React component
export function CheckoutButton({
  priceId,
  productName,
}: {
  priceId: string
  productName: string
}) {
  const [loading, setLoading] = React.useState(false)
  const [email, setEmail] = React.useState('')

  const handleCheckout = async () => {
    if (!email) return
    setLoading(true)
    try {
      await gateway.redirectToCheckout({
        priceId,
        customerEmail: email,
        successUrl: `${window.location.origin}/success?order_id={ORDER_ID}`,
        cancelUrl: window.location.href,
        description: productName,  // ← Shows as "[Website] Product Name" in Stripe
        metadata: {
          source_page: window.location.pathname,
          utm_source: new URLSearchParams(window.location.search).get('utm_source') || '',
        },
      })
    } catch (err) {
      console.error(err)
      setLoading(false)
    }
  }

  return (
    <div>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="your@email.com"
      />
      <button onClick={handleCheckout} disabled={loading}>
        {loading ? 'Redirecting…' : `Buy ${productName}`}
      </button>
    </div>
  )
}

// Success page — poll for payment confirmation
export async function SuccessPage({
  searchParams,
}: {
  searchParams: { order_id?: string }
}) {
  const orderId = searchParams.order_id

  if (!orderId) return <p>Thank you!</p>

  // Server-side check (Next.js App Router)
  const res = await fetch(
    `${process.env.NEXT_PUBLIC_GATEWAY_URL}/api/plugin/order-status?order_id=${orderId}`,
    { headers: { 'X-API-Key': process.env.GATEWAY_API_KEY! } }
  )
  const order = await res.json()

  return (
    <div>
      <h1>Payment {order.status === 'completed' ? 'Successful ✓' : 'Processing…'}</h1>
      <p>Product: {order.product?.name}</p>
      <p>Amount: {(order.amount / 100).toFixed(2)} {order.currency.toUpperCase()}</p>
    </div>
  )
}

// ─────────────────────────────────────────────
// 3. SUBSCRIPTION WITH TRIAL
// ─────────────────────────────────────────────
async function startFreeTrial(email: string) {
  const session = await gateway.createSubscription({
    priceId: 'price_monthly_plan',
    customerEmail: email,
    successUrl: 'https://yoursite.com/welcome',
    cancelUrl: 'https://yoursite.com/pricing',
    trialDays: 14,
    description: 'Pro Plan — 14-day free trial',
  })
  window.location.href = session.checkoutUrl
}

// ─────────────────────────────────────────────
// 4. CANCEL SUBSCRIPTION
// ─────────────────────────────────────────────
async function handleCancel(subscriptionId: string) {
  const result = await gateway.cancelSubscription({
    subscriptionId,
    immediately: false,  // Cancels at end of billing period
  })
  console.log('Cancels at period end:', result.cancelAtPeriodEnd)
}

// ─────────────────────────────────────────────
// 5. ISSUE A REFUND
// ─────────────────────────────────────────────
async function refundOrder(orderId: string, partialAmount?: number) {
  const refund = await gateway.refund({
    orderId,
    amount: partialAmount,  // Omit for full refund
    reason: 'requested_by_customer',
  })
  console.log('Refund issued:', refund.refundId)
}

// ─────────────────────────────────────────────
// 6. LARAVEL / PHP (server-side, via HTTP)
// ─────────────────────────────────────────────
/*
<?php
function createGatewayCheckout($priceId, $customerEmail, $description) {
    $response = Http::withHeaders([
        'X-API-Key' => config('services.gateway.api_key'),
        'Content-Type' => 'application/json',
    ])->post(config('services.gateway.url') . '/api/plugin/create-checkout', [
        'priceId'       => $priceId,
        'customerEmail' => $customerEmail,
        'successUrl'    => route('payment.success'),
        'cancelUrl'     => route('pricing'),
        'description'   => $description,
        'metadata'      => [
            'user_id'  => auth()->id(),
            'plan'     => $priceId,
        ],
    ]);

    if ($response->successful()) {
        return redirect($response->json('checkoutUrl'));
    }

    return back()->withErrors(['payment' => 'Failed to create checkout session.']);
}

// In routes/web.php:
// Route::post('/checkout', fn() => createGatewayCheckout(
//     request('price_id'),
//     auth()->user()->email,
//     'Premium Plan'
// ));
*/

// ─────────────────────────────────────────────
// 7. SHOPIFY (App Bridge / theme extension)
// ─────────────────────────────────────────────
/*
// In your Shopify theme's JavaScript:
const gateway = new StripeGateway(
  '{{ settings.gateway_api_key }}',
  '{{ settings.gateway_hub_url }}'
)

document.querySelectorAll('[data-gateway-price]').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const priceId = e.target.dataset.gatewayPrice
    const email = document.getElementById('customer-email')?.value

    const session = await gateway.createCheckout({
      priceId,
      customerEmail: email || '',
      successUrl: `${window.location.origin}/pages/order-confirmed`,
      cancelUrl: window.location.href,
      description: e.target.dataset.productName || 'Shopify Purchase',
    })
    window.location.href = session.checkoutUrl
  })
})
*/
