import { NextRequest } from 'next/server'
import { constructWebhookEvent } from '@/lib/stripe'
import { prisma } from '@/lib/prisma'
import Stripe from 'stripe'
import { Prisma } from '@prisma/client'

export async function POST(request: NextRequest) {
  const payload = await request.text()
  const signature = request.headers.get('stripe-signature')

  if (!signature) {
    return Response.json({ error: 'Missing stripe-signature' }, { status: 400 })
  }

  let event: Stripe.Event
  try {
    event = await constructWebhookEvent(payload, signature)
  } catch (err) {
    console.error('[Webhook] Signature verification failed:', err)
    return Response.json({ error: 'Invalid signature' }, { status: 400 })
  }

  // Idempotency: skip already-processed events
  const existing = await prisma.webhookEvent.findUnique({
    where: { stripeEventId: event.id },
  })

  if (existing?.processed) {
    return Response.json({ received: true, skipped: 'already processed' })
  }

  // Record the event
  await prisma.webhookEvent.upsert({
    where: { stripeEventId: event.id },
    create: {
      stripeEventId: event.id,
      type: event.type,
      payload: event as unknown as Prisma.InputJsonValue,
      processed: false,
    },
    update: {},
  })

  try {
    await handleWebhookEvent(event)

    await prisma.webhookEvent.update({
      where: { stripeEventId: event.id },
      data: { processed: true, processedAt: new Date() },
    })
  } catch (err) {
    console.error(`[Webhook] Failed to process ${event.type}:`, err)
    await prisma.webhookEvent.update({
      where: { stripeEventId: event.id },
      data: { error: (err as Error).message },
    })
    // Return 200 to prevent Stripe from retrying for processing errors
    return Response.json({ received: true, error: 'Processing failed' })
  }

  return Response.json({ received: true })
}

async function handleWebhookEvent(event: Stripe.Event) {
  switch (event.type) {
    case 'checkout.session.completed': {
      const session = event.data.object as Stripe.Checkout.Session
      await handleCheckoutComplete(session)
      break
    }

    case 'checkout.session.expired': {
      const session = event.data.object as Stripe.Checkout.Session
      await prisma.order.updateMany({
        where: { stripeCheckoutSessionId: session.id },
        data: { status: 'EXPIRED' },
      })
      break
    }

    case 'payment_intent.payment_failed': {
      const pi = event.data.object as Stripe.PaymentIntent
      await prisma.order.updateMany({
        where: { stripePaymentIntentId: pi.id },
        data: { status: 'FAILED' },
      })
      break
    }

    case 'customer.subscription.created':
    case 'customer.subscription.updated': {
      const sub = event.data.object as Stripe.Subscription
      await handleSubscriptionUpdate(sub)
      break
    }

    case 'customer.subscription.deleted': {
      const sub = event.data.object as Stripe.Subscription
      await prisma.subscription.updateMany({
        where: { stripeSubscriptionId: sub.id },
        data: { status: 'CANCELED', canceledAt: new Date() },
      })
      break
    }

    case 'invoice.payment_failed': {
      const invoice = event.data.object as Stripe.Invoice
      if (invoice.subscription) {
        await prisma.subscription.updateMany({
          where: { stripeSubscriptionId: invoice.subscription as string },
          data: { status: 'PAST_DUE' },
        })
      }
      break
    }

    case 'charge.refunded': {
      const charge = event.data.object as Stripe.Charge
      if (charge.payment_intent) {
        const refundedAmount = charge.amount_refunded
        const totalAmount = charge.amount
        await prisma.order.updateMany({
          where: { stripePaymentIntentId: charge.payment_intent as string },
          data: {
            status:
              refundedAmount >= totalAmount ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
          },
        })
      }
      break
    }

    default:
      console.log(`[Webhook] Unhandled event: ${event.type}`)
  }
}

async function handleCheckoutComplete(session: Stripe.Checkout.Session) {
  const orderId = session.metadata?.order_id
  const tenantSlug = session.metadata?.tenant

  if (!orderId) {
    console.warn('[Webhook] checkout.session.completed missing order_id metadata')
    return
  }

  const order = await prisma.order.findFirst({
    where: { stripeCheckoutSessionId: session.id },
  })

  if (!order) {
    console.warn(`[Webhook] Order not found for session ${session.id}`)
    return
  }

  // Update order status
  await prisma.order.update({
    where: { id: order.id },
    data: {
      status: 'COMPLETED',
      stripePaymentIntentId:
        typeof session.payment_intent === 'string'
          ? session.payment_intent
          : session.payment_intent?.id,
    },
  })

  // Handle subscription creation
  if (session.mode === 'subscription' && session.subscription) {
    const stripeSubId =
      typeof session.subscription === 'string'
        ? session.subscription
        : session.subscription.id

    // Find the customer
    const customer = await prisma.customer.findFirst({
      where: {
        stripeCustomerId:
          typeof session.customer === 'string'
            ? session.customer
            : session.customer?.id,
      },
    })

    if (customer) {
      // Get subscription details from Stripe
      const { stripe } = await import('@/lib/stripe')
      const stripeSub = await stripe.subscriptions.retrieve(stripeSubId)

      await prisma.subscription.upsert({
        where: { stripeSubscriptionId: stripeSubId },
        create: {
          tenantId: order.tenantId,
          customerId: customer.id,
          stripeSubscriptionId: stripeSubId,
          stripePriceId: stripeSub.items.data[0]?.price.id || '',
          status: mapSubStatus(stripeSub.status),
          currentPeriodStart: new Date(stripeSub.current_period_start * 1000),
          currentPeriodEnd: new Date(stripeSub.current_period_end * 1000),
          metadata: session.metadata ?? undefined,
        },
        update: {
          status: mapSubStatus(stripeSub.status),
          currentPeriodStart: new Date(stripeSub.current_period_start * 1000),
          currentPeriodEnd: new Date(stripeSub.current_period_end * 1000),
        },
      })
    }
  }
}

async function handleSubscriptionUpdate(sub: Stripe.Subscription) {
  const existing = await prisma.subscription.findUnique({
    where: { stripeSubscriptionId: sub.id },
  })

  if (existing) {
    await prisma.subscription.update({
      where: { stripeSubscriptionId: sub.id },
      data: {
        status: mapSubStatus(sub.status),
        currentPeriodStart: new Date(sub.current_period_start * 1000),
        currentPeriodEnd: new Date(sub.current_period_end * 1000),
        cancelAtPeriodEnd: sub.cancel_at_period_end,
      },
    })
  }
}

function mapSubStatus(
  status: Stripe.Subscription.Status
): 'ACTIVE' | 'PAST_DUE' | 'CANCELED' | 'UNPAID' | 'TRIALING' | 'PAUSED' {
  const map: Record<string, 'ACTIVE' | 'PAST_DUE' | 'CANCELED' | 'UNPAID' | 'TRIALING' | 'PAUSED'> = {
    active: 'ACTIVE',
    past_due: 'PAST_DUE',
    canceled: 'CANCELED',
    unpaid: 'UNPAID',
    trialing: 'TRIALING',
    paused: 'PAUSED',
    incomplete: 'PAST_DUE',
    incomplete_expired: 'CANCELED',
  }
  return map[status] || 'ACTIVE'
}
