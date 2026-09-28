import Stripe from 'stripe'

if (!process.env.STRIPE_SECRET_KEY) {
  throw new Error('STRIPE_SECRET_KEY is not set')
}

export const stripe = new Stripe(process.env.STRIPE_SECRET_KEY, {
  apiVersion: '2024-11-20.acacia',
  typescript: true,
})

export interface CreateCheckoutParams {
  priceId: string
  customerEmail: string
  successUrl: string
  cancelUrl: string
  tenantName: string
  tenantSlug: string
  orderId: string
  metadata?: Record<string, string>
  description?: string
  customerName?: string
}

export interface CreateSubscriptionCheckoutParams extends CreateCheckoutParams {
  trialDays?: number
}

export async function createCheckoutSession(params: CreateCheckoutParams) {
  const {
    priceId,
    customerEmail,
    successUrl,
    cancelUrl,
    tenantName,
    tenantSlug,
    orderId,
    metadata = {},
    description,
    customerName,
  } = params

  // Find or create Stripe customer
  const customers = await stripe.customers.list({
    email: customerEmail,
    limit: 1,
  })

  let customer: Stripe.Customer
  if (customers.data.length > 0) {
    customer = customers.data[0]
    // Update name if provided
    if (customerName && customer.name !== customerName) {
      customer = await stripe.customers.update(customer.id, { name: customerName })
    }
  } else {
    customer = await stripe.customers.create({
      email: customerEmail,
      name: customerName,
      metadata: {
        source: 'stripe_gateway_hub',
        tenant: tenantSlug,
      },
    })
  }

  const session = await stripe.checkout.sessions.create({
    customer: customer.id,
    line_items: [{ price: priceId, quantity: 1 }],
    mode: 'payment',
    success_url: `${successUrl}?order_id=${orderId}&session_id={CHECKOUT_SESSION_ID}`,
    cancel_url: cancelUrl,
    // ✅ Custom description visible in Stripe Dashboard
    payment_intent_data: {
      description: description
        ? `[${tenantName}] ${description}`
        : `Payment via ${tenantName} · Order ${orderId}`,
      metadata: {
        order_id: orderId,
        tenant: tenantSlug,
        tenant_name: tenantName,
        source: 'stripe_gateway_hub',
        ...metadata,
      },
      statement_descriptor_suffix: tenantSlug.toUpperCase().slice(0, 10),
    },
    metadata: {
      order_id: orderId,
      tenant: tenantSlug,
      tenant_name: tenantName,
      source: 'stripe_gateway_hub',
      ...metadata,
    },
    custom_text: {
      submit: {
        message: `You are purchasing from ${tenantName} via Stripe Gateway Hub`,
      },
    },
  })

  return { session, customerId: customer.id }
}

export async function createSubscriptionCheckoutSession(
  params: CreateSubscriptionCheckoutParams
) {
  const {
    priceId,
    customerEmail,
    successUrl,
    cancelUrl,
    tenantName,
    tenantSlug,
    orderId,
    metadata = {},
    description,
    customerName,
    trialDays,
  } = params

  const customers = await stripe.customers.list({
    email: customerEmail,
    limit: 1,
  })

  let customer: Stripe.Customer
  if (customers.data.length > 0) {
    customer = customers.data[0]
  } else {
    customer = await stripe.customers.create({
      email: customerEmail,
      name: customerName,
      metadata: {
        source: 'stripe_gateway_hub',
        tenant: tenantSlug,
      },
    })
  }

  const session = await stripe.checkout.sessions.create({
    customer: customer.id,
    line_items: [{ price: priceId, quantity: 1 }],
    mode: 'subscription',
    success_url: `${successUrl}?order_id=${orderId}&session_id={CHECKOUT_SESSION_ID}`,
    cancel_url: cancelUrl,
    subscription_data: {
      description: description
        ? `[${tenantName}] ${description}`
        : `Subscription via ${tenantName} · ${orderId}`,
      metadata: {
        order_id: orderId,
        tenant: tenantSlug,
        tenant_name: tenantName,
        source: 'stripe_gateway_hub',
        ...metadata,
      },
      trial_period_days: trialDays,
    },
    metadata: {
      order_id: orderId,
      tenant: tenantSlug,
      tenant_name: tenantName,
      source: 'stripe_gateway_hub',
      ...metadata,
    },
  })

  return { session, customerId: customer.id }
}

export async function cancelSubscription(stripeSubscriptionId: string) {
  return stripe.subscriptions.update(stripeSubscriptionId, {
    cancel_at_period_end: true,
  })
}

export async function cancelSubscriptionImmediately(stripeSubscriptionId: string) {
  return stripe.subscriptions.cancel(stripeSubscriptionId)
}

export async function createRefund(
  paymentIntentId: string,
  amount?: number,
  reason?: string
) {
  return stripe.refunds.create({
    payment_intent: paymentIntentId,
    amount,
    reason: (reason as Stripe.RefundCreateParams.Reason) || 'requested_by_customer',
  })
}

export async function getCheckoutSession(sessionId: string) {
  return stripe.checkout.sessions.retrieve(sessionId, {
    expand: ['payment_intent', 'subscription', 'customer'],
  })
}

export async function constructWebhookEvent(
  payload: string | Buffer,
  signature: string
) {
  return stripe.webhooks.constructEvent(
    payload,
    signature,
    process.env.STRIPE_WEBHOOK_SECRET!
  )
}

export async function createProduct(name: string, description?: string) {
  return stripe.products.create({ name, description })
}

export async function createPrice(
  productId: string,
  amount: number,
  currency: string,
  billingType: 'one_time' | 'recurring',
  interval?: 'day' | 'week' | 'month' | 'year'
) {
  return stripe.prices.create({
    product: productId,
    unit_amount: amount,
    currency,
    recurring:
      billingType === 'recurring' && interval ? { interval } : undefined,
  })
}
