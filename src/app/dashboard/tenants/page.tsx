'use client'

import { useState } from 'react'
import { Globe, Plus, Key, CheckCircle, XCircle, Copy, Eye, EyeOff } from 'lucide-react'

interface Tenant {
  id: string
  name: string
  slug: string
  domain: string
  active: boolean
  apiKeyPrefix: string
  orders: number
  revenue: number
  customers: number
  createdAt: string
}

const MOCK_TENANTS: Tenant[] = [
  { id: '1', name: 'TechNova SaaS', slug: 'technova', domain: 'technova.io', active: true, apiKeyPrefix: 'sgw_a1b2c3d4', orders: 387, revenue: 8420000, customers: 124, createdAt: '2024-01-15' },
  { id: '2', name: 'Digital Courses Hub', slug: 'digitalcourses', domain: 'digitalcourses.com', active: true, apiKeyPrefix: 'sgw_e5f6g7h8', orders: 189, revenue: 6240000, customers: 89, createdAt: '2024-02-03' },
  { id: '3', name: 'CloudStore Pro', slug: 'cloudstore', domain: 'cloudstore.pro', active: true, apiKeyPrefix: 'sgw_i9j0k1l2', orders: 276, revenue: 3860000, customers: 198, createdAt: '2024-02-18' },
  { id: '4', name: 'Analytics Wizard', slug: 'analyticswizard', domain: 'analyticswizard.app', active: true, apiKeyPrefix: 'sgw_m3n4o5p6', orders: 198, revenue: 2930000, customers: 76, createdAt: '2024-03-01' },
  { id: '5', name: 'Design Assets Co', slug: 'designassets', domain: 'designassets.co', active: true, apiKeyPrefix: 'sgw_q7r8s9t0', orders: 134, revenue: 1850000, customers: 98, createdAt: '2024-03-12' },
  { id: '6', name: 'FitTrack App', slug: 'fittrack', domain: 'fittrack.health', active: true, apiKeyPrefix: 'sgw_u1v2w3x4', orders: 245, revenue: 2240000, customers: 167, createdAt: '2024-03-25' },
  { id: '7', name: 'LegalDocs Online', slug: 'legaldocs', domain: 'legaldocs.online', active: true, apiKeyPrefix: 'sgw_y5z6a7b8', orders: 88, revenue: 1340000, customers: 54, createdAt: '2024-04-08' },
  { id: '8', name: 'Email Marketing Hub', slug: 'emailhub', domain: 'emailhub.io', active: true, apiKeyPrefix: 'sgw_c9d0e1f2', orders: 312, revenue: 4810000, customers: 201, createdAt: '2024-04-15' },
  { id: '9', name: 'Resume Builder AI', slug: 'resumebuilder', domain: 'resumebuilder.ai', active: true, apiKeyPrefix: 'sgw_g3h4i5j6', orders: 156, revenue: 1290000, customers: 134, createdAt: '2024-05-01' },
  { id: '10', name: 'API Gateway Services', slug: 'apigateway', domain: 'apigateway.services', active: false, apiKeyPrefix: 'sgw_k7l8m9n0', orders: 62, revenue: 960000, customers: 31, createdAt: '2024-05-10' },
]

const fmt = (cents: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(cents / 100)

export default function TenantsPage() {
  const [tenants] = useState(MOCK_TENANTS)
  const [showNewForm, setShowNewForm] = useState(false)
  const [copied, setCopied] = useState<string | null>(null)

  const copy = (text: string, id: string) => {
    navigator.clipboard.writeText(text)
    setCopied(id)
    setTimeout(() => setCopied(null), 2000)
  }

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Connected Websites</h1>
          <p className="text-sm text-gray-500 mt-1">
            {tenants.filter(t => t.active).length} active · Each site uses one API key to route payments through this hub
          </p>
        </div>
        <button
          onClick={() => setShowNewForm(true)}
          className="flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors"
        >
          <Plus className="w-4 h-4" />
          Add Website
        </button>
      </div>

      {/* Architecture note */}
      <div className="bg-violet-50 border border-violet-200 rounded-xl p-4 mb-6 flex items-start gap-3">
        <Key className="w-4 h-4 text-violet-600 mt-0.5 shrink-0" />
        <p className="text-sm text-violet-700">
          Each website gets a unique API key. They send checkout requests here — your Stripe keys never leave this hub.
          All payments appear in one Stripe account with the website name as description.
        </p>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50">
              <th className="text-left px-5 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Website</th>
              <th className="text-left px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">API Key</th>
              <th className="text-right px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Orders</th>
              <th className="text-right px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Revenue</th>
              <th className="text-right px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Customers</th>
              <th className="text-center px-4 py-3.5 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
              <th className="px-4 py-3.5" />
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {tenants.map((t) => (
              <tr key={t.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-5 py-4">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                      <Globe className="w-4 h-4 text-gray-500" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">{t.name}</p>
                      <p className="text-xs text-gray-400">{t.domain}</p>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-4">
                  <div className="flex items-center gap-2">
                    <code className="text-xs bg-gray-100 px-2 py-1 rounded text-gray-600 font-mono">
                      {t.apiKeyPrefix}••••
                    </code>
                    <button
                      onClick={() => copy(t.apiKeyPrefix, t.id)}
                      className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      {copied === t.id ? (
                        <CheckCircle className="w-3.5 h-3.5 text-green-500" />
                      ) : (
                        <Copy className="w-3.5 h-3.5" />
                      )}
                    </button>
                  </div>
                </td>
                <td className="px-4 py-4 text-right text-gray-700">{t.orders.toLocaleString()}</td>
                <td className="px-4 py-4 text-right font-medium text-gray-900">{fmt(t.revenue)}</td>
                <td className="px-4 py-4 text-right text-gray-600">{t.customers}</td>
                <td className="px-4 py-4 text-center">
                  {t.active ? (
                    <span className="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full border border-green-200">
                      <CheckCircle className="w-3 h-3" />
                      Active
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 text-xs bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">
                      <XCircle className="w-3 h-3" />
                      Inactive
                    </span>
                  )}
                </td>
                <td className="px-4 py-4 text-right">
                  <button className="text-xs text-violet-600 hover:text-violet-700 font-medium">
                    Manage
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
