import { NextRequest } from 'next/server'
import { requireApiKey, apiError, ApiValidationError, rateLimit } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { createCheckoutSession } from '@/lib/stripe'

export async function POST(request: NextRequest) {
  try {
    const ip = request.headers.get('x-forwarded-for') || 'unknown'
    if (!rateLimit(`checkout:${ip}`, 30, 60_000)) {
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
    } = body

    // Validate required fields
    if (!priceId) throw new ApiValidationError('priceId is required')
    if (!customerEmail) throw new ApiValidationError('customerEmail is required')
    if (!successUrl) throw new ApiValidationError('successUrl is required')
    if (!cancelUrl) throw new ApiValidationError('cancelUrl is required')

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(customerEmail)) {
      throw new ApiValidationError('Invalid customerEmail format')
    }

    // Find the product/price in our database
    const product = await prisma.product.findFirst({
      where: {
        tenantId: tenant.tenantId,
        stripePriceId: priceId,
        active: true,
      },
    })

    if (!product) {
      throw new ApiValidationError(
        `Price "${priceId}" not found or not active for your account`
      )
    }

    // Find or create customer in our database
    let customer = await prisma.customer.findUnique({
      where: {
        tenantId_email: {
          tenantId: tenant.tenantId,
          email: customerEmail,
        },
      },
    })

    // Create order record first (we need the ID for Stripe metadata)
    const order = await prisma.order.create({
      data: {
        tenantId: tenant.tenantId,
        customerId: customer?.id,
        productId: product.id,
        stripeCheckoutSessionId: `pending_${Date.now()}_${Math.random().toString(36).slice(2)}`,
        amount: product.amount,
        currency: product.currency,
        status: 'PENDING',
        customerEmail,
        description,
        metadata: {
          ...metadata,
          source_domain: request.headers.get('origin') || 'unknown',
        },
        successUrl,
        cancelUrl,
      },
    })

    // Create Stripe checkout session
    const { session, customerId: stripeCustomerId } = await createCheckoutSession({
      priceId,
      customerEmail,
      successUrl,
      cancelUrl,
      tenantName: tenant.tenantName,
      tenantSlug: tenant.tenantSlug,
      orderId: order.id,
      metadata: {
        ...metadata,
        // These appear in the Stripe Dashboard payment description
        website: request.headers.get('origin') || 'unknown',
        product_name: product.name,
      },
      description: description || product.name,
      customerName,
    })

    // Update order with the real Stripe session ID
    await prisma.order.update({
      where: { id: order.id },
      data: { stripeCheckoutSessionId: session.id },
    })

    // Upsert customer with Stripe ID
    if (!customer) {
      customer = await prisma.customer.create({
        data: {
          tenantId: tenant.tenantId,
          stripeCustomerId,
          email: customerEmail,
          name: customerName,
        },
      })
      // Link customer to order
      await prisma.order.update({
        where: { id: order.id },
        data: { customerId: customer.id },
      })
    } else if (!customer.stripeCustomerId) {
      await prisma.customer.update({
        where: { id: customer.id },
        data: { stripeCustomerId, name: customerName || customer.name },
      })
    }

    return Response.json({
      success: true,
      orderId: order.id,
      checkoutUrl: session.url,
      sessionId: session.id,
      expiresAt: new Date(session.expires_at * 1000).toISOString(),
    })
  } catch (error) {
    return apiError(error)
  }
}
