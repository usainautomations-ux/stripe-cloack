import { NextRequest } from 'next/server'
import { requireApiKey, apiError, rateLimit } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

export async function POST(request: NextRequest) {
  try {
    const ip = request.headers.get('x-forwarded-for') || 'unknown'
    if (!rateLimit(`connect:${ip}`, 20, 60_000)) {
      return Response.json({ error: 'Rate limit exceeded' }, { status: 429 })
    }

    const tenant = await requireApiKey(request)

    const fullTenant = await prisma.tenant.findUnique({
      where: { id: tenant.tenantId },
      select: {
        id: true,
        name: true,
        slug: true,
        domain: true,
        branding: true,
        active: true,
        _count: {
          select: { products: true, orders: true, customers: true },
        },
      },
    })

    if (!fullTenant) {
      return Response.json({ error: 'Tenant not found' }, { status: 404 })
    }

    return Response.json({
      success: true,
      tenant: {
        id: fullTenant.id,
        name: fullTenant.name,
        slug: fullTenant.slug,
        domain: fullTenant.domain,
        branding: fullTenant.branding,
        stats: {
          products: fullTenant._count.products,
          orders: fullTenant._count.orders,
          customers: fullTenant._count.customers,
        },
      },
      hub: {
        version: '1.0.0',
        capabilities: [
          'checkout',
          'subscriptions',
          'refunds',
          'order-status',
        ],
      },
    })
  } catch (error) {
    return apiError(error)
  }
}
