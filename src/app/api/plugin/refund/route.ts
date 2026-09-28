import { NextRequest } from 'next/server'
import { requireApiKey, apiError, ApiValidationError } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { createRefund } from '@/lib/stripe'

export async function POST(request: NextRequest) {
  try {
    const tenant = await requireApiKey(request)
    const body = await request.json()
    const { orderId, amount, reason } = body

    if (!orderId) throw new ApiValidationError('orderId is required')

    const order = await prisma.order.findFirst({
      where: {
        id: orderId,
        tenantId: tenant.tenantId,
        status: { in: ['COMPLETED'] },
      },
    })

    if (!order) {
      return Response.json(
        { error: 'Order not found or not eligible for refund' },
        { status: 404 }
      )
    }

    if (!order.stripePaymentIntentId) {
      return Response.json(
        { error: 'No payment intent found for this order' },
        { status: 400 }
      )
    }

    const refund = await createRefund(
      order.stripePaymentIntentId,
      amount,
      reason
    )

    const isPartial = amount && amount < order.amount
    await prisma.order.update({
      where: { id: orderId },
      data: {
        status: isPartial ? 'PARTIALLY_REFUNDED' : 'REFUNDED',
      },
    })

    return Response.json({
      success: true,
      refundId: refund.id,
      amount: refund.amount,
      currency: refund.currency,
      status: refund.status,
    })
  } catch (error) {
    return apiError(error)
  }
}
