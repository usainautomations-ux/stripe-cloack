import crypto from 'crypto'
import { prisma } from './prisma'
import { NextRequest } from 'next/server'

const API_KEY_PREFIX = 'sgw_'

export function generateApiKey(): { key: string; prefix: string; hash: string } {
  const raw = crypto.randomBytes(32).toString('hex')
  const key = `${API_KEY_PREFIX}${raw}`
  const prefix = key.slice(0, 12) // "sgw_" + first 8 hex chars
  const hash = hashApiKey(key)
  return { key, prefix, hash }
}

export function hashApiKey(key: string): string {
  const secret = process.env.API_KEY_SECRET
  if (!secret) {
    throw new Error('API_KEY_SECRET is not set')
  }
  return crypto
    .createHmac('sha256', secret)
    .update(key)
    .digest('hex')
}

export async function validateApiKey(
  key: string
): Promise<{ tenantId: string; tenantSlug: string; tenantName: string } | null> {
  if (!key || !key.startsWith(API_KEY_PREFIX)) return null

  const prefix = key.slice(0, 12)
  const hash = hashApiKey(key)

  const tenant = await prisma.tenant.findFirst({
    where: {
      apiKeyPrefix: prefix,
      apiKeyHash: hash,
      active: true,
    },
    select: {
      id: true,
      slug: true,
      name: true,
    },
  })

  if (!tenant) return null

  return {
    tenantId: tenant.id,
    tenantSlug: tenant.slug,
    tenantName: tenant.name,
  }
}

export async function requireApiKey(
  request: NextRequest
): Promise<{ tenantId: string; tenantSlug: string; tenantName: string }> {
  const apiKey =
    request.headers.get('X-API-Key') ||
    request.headers.get('x-api-key') ||
    request.headers.get('Authorization')?.replace('Bearer ', '')

  if (!apiKey) {
    throw new ApiAuthError('Missing API key. Provide X-API-Key header.')
  }

  const tenant = await validateApiKey(apiKey)
  if (!tenant) {
    throw new ApiAuthError('Invalid or inactive API key.')
  }

  return tenant
}

export class ApiAuthError extends Error {
  status = 401
  constructor(message: string) {
    super(message)
    this.name = 'ApiAuthError'
  }
}

export class ApiValidationError extends Error {
  status = 400
  constructor(message: string) {
    super(message)
    this.name = 'ApiValidationError'
  }
}

export function apiError(error: unknown) {
  if (error instanceof ApiAuthError) {
    return Response.json({ error: error.message }, { status: 401 })
  }
  if (error instanceof ApiValidationError) {
    return Response.json({ error: error.message }, { status: 400 })
  }
  console.error('[API Error]', error)
  return Response.json(
    { error: 'Internal server error' },
    { status: 500 }
  )
}

// Simple in-memory rate limiter (use Redis in production)
const rateLimitStore = new Map<string, { count: number; resetAt: number }>()

export function rateLimit(
  key: string,
  maxRequests = 100,
  windowMs = 60_000
): boolean {
  const now = Date.now()
  const entry = rateLimitStore.get(key)

  if (!entry || now > entry.resetAt) {
    rateLimitStore.set(key, { count: 1, resetAt: now + windowMs })
    return true
  }

  if (entry.count >= maxRequests) return false

  entry.count++
  return true
}
