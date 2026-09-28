/**
 * Stripe Gateway Hub SDK
 * Install on any website to connect to your central payment hub.
 *
 * Usage:
 *   const gateway = new StripeGateway('sgw_your_api_key', 'https://payments.example.com')
 *   const session = await gateway.createCheckout({ ... })
 *   window.location.href = session.checkoutUrl
 */

export interface CheckoutOptions {
  /** Stripe Price ID (e.g. "price_123abc") */
  priceId: string
  /** Customer email address */
  customerEmail: string
  /** URL to redirect after successful payment */
  successUrl: string
  /** URL to redirect if customer cancels */
  cancelUrl: string
  /** Optional customer full name */
  customerName?: string
  /** Optional payment description (shown in Stripe Dashboard) */
  description?: string
  /** Optional arbitrary metadata (key/value strings) */
  metadata?: Record<string, string>
}

export interface SubscriptionOptions extends CheckoutOptions {
  /** Number of free trial days */
  trialDays?: number
}

export interface CheckoutResult {
  orderId: string
  checkoutUrl: string
  sessionId: string
  expiresAt: string
}

export interface OrderStatus {
  orderId: string
  status: 'pending' | 'completed' | 'failed' | 'expired' | 'refunded' | 'partially_refunded'
  amount: number
  currency: string
  customerEmail: string
  description?: string
  product?: {
    name: string
    priceId: string
    billingType: 'one_time' | 'subscription'
  }
  metadata?: Record<string, unknown>
  createdAt: string
  updatedAt: string
}

export interface CancelSubscriptionOptions {
  subscriptionId: string
  /** If true, cancels immediately. Otherwise cancels at period end. */
  immediately?: boolean
}

export interface RefundOptions {
  orderId: string
  /** Amount in smallest currency unit (cents). Omit for full refund. */
  amount?: number
  reason?: 'duplicate' | 'fraudulent' | 'requested_by_customer'
}

export interface TenantInfo {
  id: string
  name: string
  slug: string
  domain?: string
  branding?: Record<string, unknown>
  stats: {
    products: number
    orders: number
    customers: number
  }
}

export class StripeGateway {
  private apiKey: string
  private baseUrl: string

  constructor(apiKey: string, baseUrl: string = 'https://payments.example.com') {
    if (!apiKey) throw new Error('StripeGateway: apiKey is required')
    if (!baseUrl) throw new Error('StripeGateway: baseUrl is required')
    this.apiKey = apiKey
    this.baseUrl = baseUrl.replace(/\/$/, '')
  }

  private async request<T>(
    path: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseUrl}${path}`
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': this.apiKey,
        ...options.headers,
      },
    })

    const data = await response.json()

    if (!response.ok) {
      throw new StripeGatewayError(
        data.error || `Request failed with status ${response.status}`,
        response.status,
        data
      )
    }

    return data as T
  }

  /**
   * Verify connection to the hub and get tenant info.
   */
  async connect(): Promise<{ success: boolean; tenant: TenantInfo }> {
    return this.request('/api/plugin/connect', { method: 'POST' })
  }

  /**
   * Create a one-time payment checkout session.
   * Returns a URL to redirect the customer to Stripe Checkout.
   */
  async createCheckout(options: CheckoutOptions): Promise<CheckoutResult> {
    return this.request('/api/plugin/create-checkout', {
      method: 'POST',
      body: JSON.stringify(options),
    })
  }

  /**
   * Create a subscription checkout session.
   */
  async createSubscription(options: SubscriptionOptions): Promise<CheckoutResult> {
    return this.request('/api/plugin/create-subscription', {
      method: 'POST',
      body: JSON.stringify(options),
    })
  }

  /**
   * Get the current status of an order by its ID.
   */
  async getOrderStatus(orderId: string): Promise<OrderStatus> {
    return this.request(`/api/plugin/order-status?order_id=${encodeURIComponent(orderId)}`)
  }

  /**
   * Cancel a subscription.
   */
  async cancelSubscription(options: CancelSubscriptionOptions): Promise<{
    success: boolean
    subscriptionId: string
    cancelAtPeriodEnd: boolean
    status: string
  }> {
    return this.request('/api/plugin/cancel-subscription', {
      method: 'POST',
      body: JSON.stringify(options),
    })
  }

  /**
   * Issue a full or partial refund for a completed order.
   */
  async refund(options: RefundOptions): Promise<{
    success: boolean
    refundId: string
    amount: number
    currency: string
    status: string
  }> {
    return this.request('/api/plugin/refund', {
      method: 'POST',
      body: JSON.stringify(options),
    })
  }

  /**
   * Convenience: create checkout and immediately redirect.
   * Call this in a button click handler.
   */
  async redirectToCheckout(options: CheckoutOptions): Promise<void> {
    const session = await this.createCheckout(options)
    window.location.href = session.checkoutUrl
  }

  /**
   * Convenience: poll order status until it's no longer pending.
   * Useful on success pages to confirm payment.
   */
  async waitForPayment(
    orderId: string,
    options: { maxAttempts?: number; intervalMs?: number } = {}
  ): Promise<OrderStatus> {
    const { maxAttempts = 10, intervalMs = 2000 } = options

    for (let i = 0; i < maxAttempts; i++) {
      const status = await this.getOrderStatus(orderId)
      if (status.status !== 'pending') return status
      if (i < maxAttempts - 1) {
        await new Promise((r) => setTimeout(r, intervalMs))
      }
    }

    return this.getOrderStatus(orderId)
  }
}

export class StripeGatewayError extends Error {
  status: number
  data: unknown

  constructor(message: string, status: number, data?: unknown) {
    super(message)
    this.name = 'StripeGatewayError'
    this.status = status
    this.data = data
  }
}

// UMD / browser global export
if (typeof window !== 'undefined') {
  ;(window as unknown as Record<string, unknown>).StripeGateway = StripeGateway
  ;(window as unknown as Record<string, unknown>).StripeGatewayError = StripeGatewayError
}

export default StripeGateway
