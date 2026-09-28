# Stripe Gateway Hub

> One Stripe account. One central hub. Payments for 10+ websites — each site only needs an API key.

## How It Works

```
Website A (sgw_key_A) ─┐
Website B (sgw_key_B) ─┤──► payments.example.com ──► Stripe Account
Website C (sgw_key_C) ─┘         (Hub)                    ▲
                                   │                        │
                               Webhooks ───────────────────┘
```

1. Install the SDK/plugin on any website
2. Website sends checkout requests to the Hub using its API key
3. Hub creates Stripe Checkout Sessions using the central Stripe secret key
4. Customer pays on Stripe's hosted checkout page
5. Stripe webhooks return **only** to the Hub
6. Hub stores all orders, subscriptions, customers, and analytics
7. **Stripe keys never leave the Hub**

## Payment Description in Stripe

Every payment in your Stripe Dashboard shows:

```
[TechNova SaaS] Pro Plan — Annual Subscription
```

With metadata:
```json
{
  "tenant": "technova",
  "tenant_name": "TechNova SaaS",
  "order_id": "ord_clxyz123",
  "source": "stripe_gateway_hub",
  "product_name": "Pro Plan"
}
```

## Quick Start

### 1. Deploy the Hub

```bash
git clone https://github.com/yourorg/stripe-gateway-hub
cd stripe-gateway-hub
cp .env.example .env
# Edit .env with your Stripe keys
docker-compose up -d
```

### 2. Run Migrations & Seed

```bash
npm run db:migrate
npm run db:seed
# Outputs 10 tenant API keys — save them!
```

### 3. Configure Stripe Webhooks

In your [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks), add:

**Endpoint URL:** `https://payments.example.com/api/stripe/webhook`

**Events to subscribe:**
- `checkout.session.completed`
- `checkout.session.expired`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_failed`
- `payment_intent.payment_failed`
- `charge.refunded`

Copy the **Webhook Signing Secret** (`whsec_...`) into your `.env` as `STRIPE_WEBHOOK_SECRET`.

### 4. Install on a Website

#### JavaScript / React
```bash
npm install @stripe-gateway-hub/sdk
```

```js
import { StripeGateway } from '@stripe-gateway-hub/sdk'

const gateway = new StripeGateway('sgw_your_key', 'https://payments.example.com')

const session = await gateway.createCheckout({
  priceId: 'price_abc123',        // From your Stripe Dashboard
  customerEmail: 'user@site.com',
  successUrl: 'https://yoursite.com/thanks',
  cancelUrl: 'https://yoursite.com/pricing',
  description: 'Pro Plan',        // Appears in Stripe Dashboard
})
window.location.href = session.checkoutUrl
```

#### WordPress
1. Upload `wordpress-plugin/` to `/wp-content/plugins/stripe-gateway-hub/`
2. Activate in WP Admin → Plugins
3. Go to **Settings → Stripe Gateway Hub**, enter your Hub URL and API key
4. Add shortcode to any page:

```
[sgh_checkout price_id="price_abc123" label="Buy Now" description="Premium Plan"]
```

Add `[sgh_payment_success]` to your success page.

#### Vanilla HTML
```html
<script src="https://unpkg.com/@stripe-gateway-hub/sdk/dist/index.umd.js"></script>
<script>
  const gateway = new StripeGateway('sgw_your_key', 'https://payments.example.com')

  async function checkout() {
    const session = await gateway.createCheckout({
      priceId: 'price_abc123',
      customerEmail: document.getElementById('email').value,
      successUrl: window.location.origin + '/success',
      cancelUrl: window.location.href,
    })
    window.location.href = session.checkoutUrl
  }
</script>
```

#### PHP / Laravel
```php
$response = Http::withHeaders([
    'X-API-Key' => env('GATEWAY_API_KEY'),
])->post('https://payments.example.com/api/plugin/create-checkout', [
    'priceId'       => 'price_abc123',
    'customerEmail' => $user->email,
    'successUrl'    => route('payment.success'),
    'cancelUrl'     => route('pricing'),
    'description'   => 'Pro Plan',
]);

return redirect($response->json('checkoutUrl'));
```

## API Reference

All endpoints require `X-API-Key: sgw_your_key` header.

### POST /api/plugin/connect
Test connection and get tenant info.

### POST /api/plugin/create-checkout
```json
{
  "priceId": "price_abc123",
  "customerEmail": "user@example.com",
  "successUrl": "https://site.com/success",
  "cancelUrl": "https://site.com/cancel",
  "customerName": "John Smith",
  "description": "Pro Plan Purchase",
  "metadata": { "user_id": "123" }
}
```
Returns: `{ orderId, checkoutUrl, sessionId, expiresAt }`

### POST /api/plugin/create-subscription
Same as create-checkout, plus optional `trialDays`.

### GET /api/plugin/order-status?order_id=ord_xxx
Returns order status: `pending | completed | failed | expired | refunded`

### POST /api/plugin/cancel-subscription
```json
{ "subscriptionId": "sub_xxx", "immediately": false }
```

### POST /api/plugin/refund
```json
{ "orderId": "ord_xxx", "amount": 1000, "reason": "requested_by_customer" }
```

## Architecture

```
stripe-gateway-hub/
├── prisma/
│   └── schema.prisma          # DB models: Tenant, Product, Customer, Order, Subscription, WebhookEvent
├── src/
│   ├── app/
│   │   ├── api/
│   │   │   ├── plugin/        # Plugin API endpoints (require X-API-Key)
│   │   │   │   ├── connect/
│   │   │   │   ├── create-checkout/
│   │   │   │   ├── create-subscription/
│   │   │   │   ├── order-status/
│   │   │   │   ├── cancel-subscription/
│   │   │   │   └── refund/
│   │   │   └── stripe/
│   │   │       └── webhook/   # Stripe webhook handler (signature verified)
│   │   └── dashboard/         # Admin UI
│   └── lib/
│       ├── stripe.ts           # Stripe service layer
│       ├── auth.ts             # API key hashing & validation
│       └── prisma.ts           # Prisma singleton
├── sdk/
│   ├── index.ts               # JavaScript/TypeScript SDK
│   └── examples.ts            # Integration examples
├── wordpress-plugin/
│   └── stripe-gateway-hub.php # WordPress plugin
├── scripts/
│   └── seed.ts                # Seeds 10 tenants
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

## Security Model

| Concern | Implementation |
|---------|----------------|
| Stripe secret keys | Stored only in Hub `.env`, never transmitted to client sites |
| API keys | HMAC-SHA256 hashed with `API_KEY_SECRET` before storage |
| Webhook validation | Stripe signature verified on every request |
| Tenant isolation | Every query scoped to `tenantId` from validated API key |
| Rate limiting | Per-IP rate limiting on all plugin endpoints |
| Idempotency | Webhook events deduplicated by Stripe event ID |
| Audit logs | All API actions logged with IP and timestamp |

## Environment Variables

See `.env.example` for the full list. Required:

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | PostgreSQL connection string |
| `STRIPE_SECRET_KEY` | Your Stripe secret key (`sk_live_...`) |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret (`whsec_...`) |
| `API_KEY_SECRET` | Secret used to hash tenant API keys (min 32 chars) |
| `NEXTAUTH_SECRET` | NextAuth session secret |

## Deployment

### Vercel
```bash
vercel deploy
# Set environment variables in Vercel dashboard
# Set up Vercel Postgres or external PostgreSQL
```

### Docker
```bash
docker-compose up -d
# Runs app + PostgreSQL + Redis
```

### Manual
```bash
npm install
npx prisma migrate deploy
npm run build
npm start
```
