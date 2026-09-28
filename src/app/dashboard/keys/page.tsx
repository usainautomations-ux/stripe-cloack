'use client'

import { useState } from 'react'
import { Key, Plus, Copy, CheckCircle, AlertTriangle, Eye, EyeOff, Trash2 } from 'lucide-react'

export default function ApiKeysPage() {
  const [showRotate, setShowRotate] = useState<string | null>(null)
  const [copied, setCopied] = useState<string | null>(null)

  const copy = (text: string, id: string) => {
    navigator.clipboard.writeText(text)
    setCopied(id)
    setTimeout(() => setCopied(null), 2000)
  }

  const tenants = [
    { id: '1', name: 'TechNova SaaS', slug: 'technova', prefix: 'sgw_a1b2c3d4', lastUsed: '2024-12-01', requests: 2847 },
    { id: '2', name: 'Digital Courses Hub', slug: 'digitalcourses', prefix: 'sgw_e5f6g7h8', lastUsed: '2024-12-01', requests: 1234 },
    { id: '3', name: 'CloudStore Pro', slug: 'cloudstore', prefix: 'sgw_i9j0k1l2', lastUsed: '2024-11-30', requests: 987 },
    { id: '4', name: 'Analytics Wizard', slug: 'analyticswizard', prefix: 'sgw_m3n4o5p6', lastUsed: '2024-11-30', requests: 654 },
    { id: '5', name: 'Design Assets Co', slug: 'designassets', prefix: 'sgw_q7r8s9t0', lastUsed: '2024-11-29', requests: 432 },
  ]

  return (
    <div className="p-8 max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">API Keys</h1>
          <p className="text-sm text-gray-500 mt-1">
            Each website gets one key. Keys never contain your Stripe credentials.
          </p>
        </div>
      </div>

      {/* Security model */}
      <div className="grid grid-cols-3 gap-4 mb-8">
        {[
          { icon: '🔐', title: 'Keys are hashed', desc: 'API keys are stored as HMAC-SHA256 hashes — never in plain text' },
          { icon: '🏠', title: 'Hub-side Stripe', desc: 'Stripe secret keys live only in this hub — never sent to client sites' },
          { icon: '🔑', title: 'Tenant-scoped', desc: 'Each key only accesses its own orders, customers, and products' },
        ].map((c) => (
          <div key={c.title} className="bg-white border border-gray-200 rounded-xl p-4">
            <p className="text-2xl mb-2">{c.icon}</p>
            <p className="text-sm font-semibold text-gray-800">{c.title}</p>
            <p className="text-xs text-gray-500 mt-1">{c.desc}</p>
          </div>
        ))}
      </div>

      <div className="space-y-3">
        {tenants.map((t) => (
          <div key={t.id} className="bg-white border border-gray-200 rounded-xl p-5">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-violet-100 rounded-lg flex items-center justify-center">
                  <Key className="w-4 h-4 text-violet-600" />
                </div>
                <div>
                  <p className="font-semibold text-gray-900">{t.name}</p>
                  <p className="text-xs text-gray-400">/{t.slug}</p>
                </div>
              </div>
              <div className="text-right text-xs text-gray-400">
                <p>{t.requests.toLocaleString()} requests</p>
                <p>Last used {t.lastUsed}</p>
              </div>
            </div>

            <div className="mt-4 flex items-center gap-3">
              <div className="flex-1 flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                <code className="text-sm font-mono text-gray-700 flex-1">
                  {t.prefix}••••••••••••••••••••••••
                </code>
                <button
                  onClick={() => copy(t.prefix, t.id)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  {copied === t.id
                    ? <CheckCircle className="w-4 h-4 text-green-500" />
                    : <Copy className="w-4 h-4" />}
                </button>
              </div>
              <button
                onClick={() => setShowRotate(t.id)}
                className="text-xs text-amber-600 border border-amber-200 bg-amber-50 px-3 py-2 rounded-lg hover:bg-amber-100 transition-colors"
              >
                Rotate Key
              </button>
            </div>

            {showRotate === t.id && (
              <div className="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                  <div className="text-xs text-amber-700">
                    <p className="font-semibold mb-1">Rotating will invalidate the current key immediately.</p>
                    <p>You will need to update the API key on <strong>{t.name}</strong> right away.</p>
                    <div className="flex gap-2 mt-2">
                      <button className="bg-amber-600 text-white px-3 py-1 rounded text-xs font-medium hover:bg-amber-700">
                        Confirm Rotation
                      </button>
                      <button onClick={() => setShowRotate(null)} className="text-amber-700 underline text-xs">
                        Cancel
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Usage example */}
      <div className="mt-8 bg-gray-900 rounded-xl p-5">
        <p className="text-xs text-gray-400 mb-3 font-medium">SDK Usage Example</p>
        <pre className="text-xs text-green-400 font-mono overflow-x-auto">
{`// Install on any website (React, Next.js, vanilla JS, etc.)
import { StripeGateway } from '@stripe-gateway-hub/sdk'

const gateway = new StripeGateway(
  'sgw_your_api_key_here',
  'https://payments.example.com'
)

// Create checkout — Stripe key never exposed to the browser
const session = await gateway.createCheckout({
  priceId: 'price_123abc',
  customerEmail: 'buyer@example.com',
  successUrl: 'https://yoursite.com/thank-you',
  cancelUrl: 'https://yoursite.com/pricing',
  description: 'Premium Plan - Annual',  // ← Shown in Stripe Dashboard
})

window.location.href = session.checkoutUrl`}
        </pre>
      </div>
    </div>
  )
}
