import { NextRequest } from 'next/server'
import { requireApiKey, apiError, ApiValidationError, rateLimit } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { createSubscriptionCheckoutSession } from '@/lib/stripe'

export async function POST(request: NextRequest) {
  try {
    const ip = request.headers.get('x-forwarded-for') || 'unknown'
    if (!rateLimit(`sub:${ip}`, 20, 60_000)) {
      return Response.json({ error: 'Rate limit exceeded' }, { status: 429 })
    }

    const tenant = await requireApiKey(request)
    const body = await request.json()
    const {
      priceId,
      customerEmail,
      successUrl,
      cancelUrl,
      metadata = {},
      description,
      customerName,
      trialDays,
    } = body

    if (!priceId) throw new ApiValidationError('priceId is required')
    if (!customerEmail) throw new ApiValidationError('customerEmail is required')
    if (!successUrl) throw new ApiValidationError('successUrl is required')
    if (!cancelUrl) throw new ApiValidationError('cancelUrl is required')

    const product = await prisma.product.findFirst({
      where: {
        tenantId: tenant.tenantId,
        stripePriceId: priceId,
        active: true,
        billingType: 'SUBSCRIPTION',
      },
    })

    if (!product) {
      throw new ApiValidationError(
        `Subscription price "${priceId}" not found or not active`
      )
    }

    const order = await prisma.order.create({
      data: {
        tenantId: tenant.tenantId,
        productId: product.id,
        stripeCheckoutSessionId: `pending_sub_${Date.now()}`,
        amount: product.amount,
        currency: product.currency,
        status: 'PENDING',
        customerEmail,
        description,
        metadata,
        successUrl,
        cancelUrl,
      },
    })

    const { session, customerId: stripeCustomerId } =
      await createSubscriptionCheckoutSession({
        priceId,
        customerEmail,
        successUrl,
        cancelUrl,
        tenantName: tenant.tenantName,
        tenantSlug: tenant.tenantSlug,
        orderId: order.id,
        metadata: {
          ...metadata,
          product_name: product.name,
        },
        description: description || `Subscription: ${product.name}`,
        customerName,
        trialDays,
      })

    await prisma.order.update({
      where: { id: order.id },
      data: { stripeCheckoutSessionId: session.id },
    })

    // Upsert customer
    await prisma.customer.upsert({
      where: {
        tenantId_email: { tenantId: tenant.tenantId, email: customerEmail },
      },
      create: {
        tenantId: tenant.tenantId,
        stripeCustomerId,
        email: customerEmail,
        name: customerName,
      },
      update: { stripeCustomerId, name: customerName },
    })

    return Response.json({
      success: true,
      orderId: order.id,
      checkoutUrl: session.url,
      sessionId: session.id,
    })
  } catch (error) {
    return apiError(error)
  }
}
