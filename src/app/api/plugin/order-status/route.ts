import { NextRequest } from 'next/server'
import { requireApiKey, apiError, ApiValidationError } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

export async function GET(request: NextRequest) {
  try {
    const tenant = await requireApiKey(request)
    const { searchParams } = new URL(request.url)
    const orderId = searchParams.get('order_id')

    if (!orderId) throw new ApiValidationError('order_id query parameter is required')

    const order = await prisma.order.findFirst({
      where: {
        id: orderId,
        tenantId: tenant.tenantId, // Security: ensure tenant can only see their own orders
      },
      include: {
        product: {
          select: { name: true, stripePriceId: true, billingType: true },
        },
        customer: {
          select: { email: true, name: true },
        },
      },
    })

    if (!order) {
      return Response.json({ error: 'Order not found' }, { status: 404 })
    }

    return Response.json({
      orderId: order.id,
      status: order.status.toLowerCase(),
      amount: order.amount,
      currency: order.currency,
      customerEmail: order.customerEmail,
      description: order.description,
      product: order.product
        ? {
            name: order.product.name,
            priceId: order.product.stripePriceId,
            billingType: order.product.billingType.toLowerCase(),
          }
        : null,
      customer: order.customer,
      metadata: order.metadata,
      createdAt: order.createdAt.toISOString(),
      updatedAt: order.updatedAt.toISOString(),
    })
  } catch (error) {
    return apiError(error)
  }
}
