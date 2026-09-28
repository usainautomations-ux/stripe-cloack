import { NextRequest } from 'next/server'
import { requireApiKey, apiError, ApiValidationError } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { cancelSubscription, cancelSubscriptionImmediately } from '@/lib/stripe'

export async function POST(request: NextRequest) {
  try {
    const tenant = await requireApiKey(request)
    const body = await request.json()
    const { subscriptionId, immediately = false } = body

    if (!subscriptionId) {
      throw new ApiValidationError('subscriptionId is required')
    }

    const subscription = await prisma.subscription.findFirst({
      where: {
        id: subscriptionId,
        tenantId: tenant.tenantId,
      },
    })

    if (!subscription) {
      return Response.json({ error: 'Subscription not found' }, { status: 404 })
    }

    const stripeResult = immediately
      ? await cancelSubscriptionImmediately(subscription.stripeSubscriptionId)
      : await cancelSubscription(subscription.stripeSubscriptionId)

    await prisma.subscription.update({
      where: { id: subscriptionId },
      data: {
        status: immediately ? 'CANCELED' : 'ACTIVE',
        cancelAtPeriodEnd: !immediately,
        canceledAt: immediately ? new Date() : null,
      },
    })

    return Response.json({
      success: true,
      subscriptionId,
      cancelAtPeriodEnd: stripeResult.cancel_at_period_end,
      status: stripeResult.status,
    })
  } catch (error) {
    return apiError(error)
  }
}
