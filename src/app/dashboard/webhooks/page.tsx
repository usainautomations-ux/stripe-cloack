'use client'

import { useState } from 'react'
import { CheckCircle, XCircle, Clock, RefreshCw, ChevronDown, ChevronRight } from 'lucide-react'

const MOCK_EVENTS = [
  { id: 'evt_1', stripeEventId: 'evt_1abc', type: 'checkout.session.completed', processed: true, createdAt: '2024-12-01T10:23:00Z', tenant: 'TechNova SaaS' },
  { id: 'evt_2', stripeEventId: 'evt_2abc', type: 'checkout.session.completed', processed: true, createdAt: '2024-12-01T11:45:00Z', tenant: 'Digital Courses Hub' },
  { id: 'evt_3', stripeEventId: 'evt_3abc', type: 'customer.subscription.created', processed: true, createdAt: '2024-12-01T12:00:00Z', tenant: 'CloudStore Pro' },
  { id: 'evt_4', stripeEventId: 'evt_4abc', type: 'payment_intent.payment_failed', processed: true, createdAt: '2024-12-01T13:20:00Z', tenant: 'FitTrack App' },
  { id: 'evt_5', stripeEventId: 'evt_5abc', type: 'charge.refunded', processed: true, createdAt: '2024-11-30T09:10:00Z', tenant: 'Design Assets Co' },
  { id: 'evt_6', stripeEventId: 'evt_6abc', type: 'customer.subscription.updated', processed: false, createdAt: '2024-11-30T14:35:00Z', tenant: 'Email Marketing Hub' },
  { id: 'evt_7', stripeEventId: 'evt_7abc', type: 'invoice.payment_failed', processed: false, createdAt: '2024-11-30T16:00:00Z', tenant: 'Analytics Wizard' },
]

const EVENT_COLORS: Record<string, string> = {
  'checkout.session.completed': 'text-green-600 bg-green-50',
  'customer.subscription.created': 'text-blue-600 bg-blue-50',
  'customer.subscription.updated': 'text-blue-600 bg-blue-50',
  'customer.subscription.deleted': 'text-gray-600 bg-gray-50',
  'payment_intent.payment_failed': 'text-red-600 bg-red-50',
  'invoice.payment_failed': 'text-red-600 bg-red-50',
  'charge.refunded': 'text-amber-600 bg-amber-50',
}

export default function WebhooksPage() {
  const [expanded, setExpanded] = useState<string | null>(null)

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Webhook Logs</h1>
          <p className="text-sm text-gray-500 mt-1">
            All Stripe events are received here — individual websites never see raw webhooks
          </p>
        </div>
        <button className="flex items-center gap-2 text-sm text-gray-600 border border-gray-200 px-3 py-2 rounded-lg hover:bg-gray-50">
          <RefreshCw className="w-4 h-4" />
          Refresh
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <p className="text-2xl font-semibold text-gray-900">
            {MOCK_EVENTS.filter(e => e.processed).length}
          </p>
          <p className="text-sm text-gray-500">Processed today</p>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <p className="text-2xl font-semibold text-amber-600">
            {MOCK_EVENTS.filter(e => !e.processed).length}
          </p>
          <p className="text-sm text-gray-500">Pending</p>
        </div>
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <p className="text-2xl font-semibold text-gray-900">0</p>
          <p className="text-sm text-gray-500">Errors</p>
        </div>
      </div>

      {/* Webhook endpoint */}
      <div className="bg-gray-900 text-green-400 font-mono text-xs rounded-xl px-5 py-4 mb-6">
        <span className="text-gray-500">Stripe Webhook URL → </span>
        <span>POST https://payments.example.com/api/stripe/webhook</span>
        <span className="text-gray-500 ml-4">← Configure this in your Stripe Dashboard</span>
      </div>

      {/* Events */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        {MOCK_EVENTS.map((e) => {
          const colorClass = EVENT_COLORS[e.type] || 'text-gray-600 bg-gray-50'
          const isOpen = expanded === e.id
          return (
            <div key={e.id} className="border-b border-gray-50 last:border-0">
              <button
                onClick={() => setExpanded(isOpen ? null : e.id)}
                className="w-full flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 transition-colors text-left"
              >
                <span>
                  {e.processed
                    ? <CheckCircle className="w-4 h-4 text-green-500" />
                    : <Clock className="w-4 h-4 text-amber-500" />}
                </span>
                <span className={`text-xs font-mono px-2 py-0.5 rounded ${colorClass}`}>
                  {e.type}
                </span>
                <span className="text-xs text-gray-400 font-mono">{e.stripeEventId}</span>
                <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full ml-auto">
                  {e.tenant}
                </span>
                <span className="text-xs text-gray-400 ml-2">
                  {new Date(e.createdAt).toLocaleTimeString()}
                </span>
                {isOpen ? <ChevronDown className="w-4 h-4 text-gray-400" /> : <ChevronRight className="w-4 h-4 text-gray-400" />}
              </button>
              {isOpen && (
                <div className="px-5 pb-4 bg-gray-50 border-t border-gray-100">
                  <pre className="text-xs text-gray-600 font-mono whitespace-pre-wrap mt-3">
{JSON.stringify({
  id: e.stripeEventId,
  type: e.type,
  metadata: {
    tenant: e.tenant.toLowerCase().replace(/ /g, '_'),
    source: 'stripe_gateway_hub',
  },
  created: new Date(e.createdAt).getTime() / 1000,
}, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
