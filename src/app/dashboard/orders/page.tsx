'use client'

import { useState } from 'react'
import { Search, Filter, CheckCircle, XCircle, Clock, RotateCcw, ExternalLink } from 'lucide-react'

interface Order {
  id: string
  tenant: string
  customerEmail: string
  product: string
  amount: number
  currency: string
  status: string
  description: string
  createdAt: string
}

const STATUS_CONFIG: Record<string, { label: string; class: string; icon: React.ReactNode }> = {
  COMPLETED:           { label: 'Completed', class: 'bg-green-50 text-green-700 border-green-200', icon: <CheckCircle className="w-3 h-3" /> },
  PENDING:             { label: 'Pending', class: 'bg-amber-50 text-amber-700 border-amber-200', icon: <Clock className="w-3 h-3" /> },
  FAILED:              { label: 'Failed', class: 'bg-red-50 text-red-700 border-red-200', icon: <XCircle className="w-3 h-3" /> },
  REFUNDED:            { label: 'Refunded', class: 'bg-gray-50 text-gray-600 border-gray-200', icon: <RotateCcw className="w-3 h-3" /> },
  PARTIALLY_REFUNDED:  { label: 'Part. Refunded', class: 'bg-orange-50 text-orange-700 border-orange-200', icon: <RotateCcw className="w-3 h-3" /> },
  EXPIRED:             { label: 'Expired', class: 'bg-gray-50 text-gray-400 border-gray-100', icon: <XCircle className="w-3 h-3" /> },
}

const MOCK_ORDERS: Order[] = [
  { id: 'ord_1', tenant: 'TechNova SaaS', customerEmail: 'alice@example.com', product: 'Pro Plan', amount: 9900, currency: 'USD', status: 'COMPLETED', description: '[TechNova SaaS] Pro Plan', createdAt: '2024-12-01T10:23:00Z' },
  { id: 'ord_2', tenant: 'Digital Courses Hub', customerEmail: 'bob@example.com', product: 'React Mastery Course', amount: 19700, currency: 'USD', status: 'COMPLETED', description: '[Digital Courses Hub] React Mastery Course', createdAt: '2024-12-01T11:45:00Z' },
  { id: 'ord_3', tenant: 'CloudStore Pro', customerEmail: 'carol@example.com', product: '1TB Storage', amount: 1999, currency: 'USD', status: 'PENDING', description: '[CloudStore Pro] 1TB Storage', createdAt: '2024-12-01T12:00:00Z' },
  { id: 'ord_4', tenant: 'FitTrack App', customerEmail: 'dan@example.com', product: 'Monthly Premium', amount: 1499, currency: 'USD', status: 'FAILED', description: '[FitTrack App] Monthly Premium', createdAt: '2024-12-01T13:20:00Z' },
  { id: 'ord_5', tenant: 'Design Assets Co', customerEmail: 'eve@example.com', product: 'Template Bundle', amount: 7900, currency: 'USD', status: 'REFUNDED', description: '[Design Assets Co] Template Bundle', createdAt: '2024-11-30T09:10:00Z' },
  { id: 'ord_6', tenant: 'Email Marketing Hub', customerEmail: 'frank@example.com', product: 'Up to 25,000 subscribers', amount: 5900, currency: 'USD', status: 'COMPLETED', description: '[Email Marketing Hub] 25k Plan', createdAt: '2024-11-30T14:35:00Z' },
  { id: 'ord_7', tenant: 'Analytics Wizard', customerEmail: 'grace@example.com', product: 'Business Analytics', amount: 4900, currency: 'USD', status: 'COMPLETED', description: '[Analytics Wizard] Business Analytics', createdAt: '2024-11-30T16:00:00Z' },
  { id: 'ord_8', tenant: 'Resume Builder AI', customerEmail: 'henry@example.com', product: 'Resume Download', amount: 990, currency: 'USD', status: 'COMPLETED', description: '[Resume Builder AI] Resume Download', createdAt: '2024-11-29T08:45:00Z' },
]

const fmt = (cents: number, currency = 'USD') =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100)

export default function OrdersPage() {
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('ALL')

  const filtered = MOCK_ORDERS.filter((o) => {
    const matchSearch =
      !search ||
      o.customerEmail.toLowerCase().includes(search.toLowerCase()) ||
      o.tenant.toLowerCase().includes(search.toLowerCase()) ||
      o.product.toLowerCase().includes(search.toLowerCase())
    const matchFilter = filter === 'ALL' || o.status === filter
    return matchSearch && matchFilter
  })

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Orders</h1>
          <p className="text-sm text-gray-500 mt-1">All payments across every connected website</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 mb-6">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by email, website, product…"
            className="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          />
        </div>
        <div className="flex gap-1">
          {['ALL', 'COMPLETED', 'PENDING', 'FAILED', 'REFUNDED'].map((s) => (
            <button
              key={s}
              onClick={() => setFilter(s)}
              className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${
                filter === s
                  ? 'bg-violet-600 text-white'
                  : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
              }`}
            >
              {s === 'ALL' ? 'All' : STATUS_CONFIG[s]?.label || s}
            </button>
          ))}
        </div>
      </div>

      {/* Important note */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-4 text-xs text-blue-700 flex items-center gap-2">
        <ExternalLink className="w-3.5 h-3.5 shrink-0" />
        All payments appear in Stripe with <code className="font-mono bg-blue-100 px-1 rounded">[Website Name] Product Description</code> format — visible in your Stripe Dashboard.
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50">
              <th className="text-left px-5 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Description (Stripe)</th>
              <th className="text-left px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Customer</th>
              <th className="text-left px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Website</th>
              <th className="text-right px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</th>
              <th className="text-center px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
              <th className="text-right px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {filtered.map((o) => {
              const s = STATUS_CONFIG[o.status]
              return (
                <tr key={o.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-5 py-3.5">
                    <p className="font-medium text-gray-800">{o.product}</p>
                    <p className="text-xs text-gray-400 font-mono mt-0.5">{o.description}</p>
                  </td>
                  <td className="px-4 py-3.5 text-gray-600">{o.customerEmail}</td>
                  <td className="px-4 py-3.5">
                    <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                      {o.tenant}
                    </span>
                  </td>
                  <td className="px-4 py-3.5 text-right font-semibold text-gray-900">
                    {fmt(o.amount, o.currency)}
                  </td>
                  <td className="px-4 py-3.5 text-center">
                    <span className={`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border ${s.class}`}>
                      {s.icon}
                      {s.label}
                    </span>
                  </td>
                  <td className="px-4 py-3.5 text-right text-xs text-gray-400">
                    {new Date(o.createdAt).toLocaleDateString()}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
        {filtered.length === 0 && (
          <div className="text-center py-12 text-gray-400">
            <Search className="w-8 h-8 mx-auto mb-2 opacity-40" />
            <p className="text-sm">No orders match your search</p>
          </div>
        )}
      </div>
    </div>
  )
}
