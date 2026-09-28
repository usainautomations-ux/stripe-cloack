'use client'

import { useState } from 'react'
import { Package, Plus, ExternalLink, Tag, RefreshCw } from 'lucide-react'

const MOCK_PRODUCTS = [
  { id: 'p1', tenant: 'TechNova SaaS', name: 'Starter Plan', amount: 2900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_abc', stripePriceId: 'price_abc', active: true },
  { id: 'p2', tenant: 'TechNova SaaS', name: 'Pro Plan', amount: 9900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_def', stripePriceId: 'price_def', active: true },
  { id: 'p3', tenant: 'Digital Courses Hub', name: 'React Mastery Course', amount: 19700, currency: 'usd', billingType: 'ONE_TIME', interval: null, stripeProductId: 'prod_ghi', stripePriceId: 'price_ghi', active: true },
  { id: 'p4', tenant: 'Digital Courses Hub', name: 'Full Stack Bootcamp', amount: 49700, currency: 'usd', billingType: 'ONE_TIME', interval: null, stripeProductId: 'prod_jkl', stripePriceId: 'price_jkl', active: true },
  { id: 'p5', tenant: 'CloudStore Pro', name: '100GB Storage', amount: 499, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_mno', stripePriceId: 'price_mno', active: true },
  { id: 'p6', tenant: 'FitTrack App', name: 'Annual Premium', amount: 9900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'year', stripeProductId: 'prod_pqr', stripePriceId: 'price_pqr', active: true },
  { id: 'p7', tenant: 'Email Marketing Hub', name: 'Up to 25,000 subscribers', amount: 5900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_stu', stripePriceId: 'price_stu', active: true },
  { id: 'p8', tenant: 'Resume Builder AI', name: 'Resume Download', amount: 990, currency: 'usd', billingType: 'ONE_TIME', interval: null, stripeProductId: 'prod_vwx', stripePriceId: 'price_vwx', active: false },
]

const fmt = (cents: number, currency = 'usd') =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency.toUpperCase() }).format(cents / 100)

export default function ProductsPage() {
  const [filterTenant, setFilterTenant] = useState('ALL')
  const tenants = ['ALL', ...Array.from(new Set(MOCK_PRODUCTS.map((p) => p.tenant)))]

  const filtered = MOCK_PRODUCTS.filter(
    (p) => filterTenant === 'ALL' || p.tenant === filterTenant
  )

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Products</h1>
          <p className="text-sm text-gray-500 mt-1">
            All products are backed by real Stripe Products & Prices — IDs must match your Stripe Dashboard
          </p>
        </div>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 text-sm border border-gray-200 text-gray-600 px-3 py-2 rounded-lg hover:bg-gray-50">
            <RefreshCw className="w-4 h-4" />
            Sync from Stripe
          </button>
          <button className="flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
            <Plus className="w-4 h-4" />
            Add Product
          </button>
        </div>
      </div>

      {/* Tenant filter */}
      <div className="flex gap-2 mb-6 flex-wrap">
        {tenants.map((t) => (
          <button
            key={t}
            onClick={() => setFilterTenant(t)}
            className={`text-xs px-3 py-1.5 rounded-full border transition-colors ${
              filterTenant === t
                ? 'bg-violet-600 text-white border-violet-600'
                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
            }`}
          >
            {t === 'ALL' ? 'All Websites' : t}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {filtered.map((p) => (
          <div
            key={p.id}
            className={`bg-white border rounded-xl p-5 ${
              p.active ? 'border-gray-200' : 'border-gray-100 opacity-60'
            }`}
          >
            <div className="flex items-start justify-between mb-3">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 bg-violet-50 rounded-lg flex items-center justify-center">
                  <Package className="w-4 h-4 text-violet-600" />
                </div>
                <div>
                  <p className="font-semibold text-gray-900 text-sm">{p.name}</p>
                  <p className="text-xs text-gray-400">{p.tenant}</p>
                </div>
              </div>
              <span
                className={`text-xs px-2 py-0.5 rounded-full border ${
                  p.billingType === 'SUBSCRIPTION'
                    ? 'bg-blue-50 text-blue-700 border-blue-200'
                    : 'bg-gray-50 text-gray-600 border-gray-200'
                }`}
              >
                {p.billingType === 'SUBSCRIPTION'
                  ? `/${p.interval}`
                  : 'one-time'}
              </span>
            </div>

            <p className="text-2xl font-bold text-gray-900 mb-3">
              {fmt(p.amount, p.currency)}
            </p>

            <div className="space-y-1.5 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-gray-400">Stripe Product ID</span>
                <code className="text-gray-600 font-mono bg-gray-50 px-1.5 py-0.5 rounded">
                  {p.stripeProductId}
                </code>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-400">Stripe Price ID</span>
                <code className="text-violet-600 font-mono bg-violet-50 px-1.5 py-0.5 rounded">
                  {p.stripePriceId}
                </code>
              </div>
            </div>

            <div className="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
              <span className={`text-xs ${p.active ? 'text-green-600' : 'text-gray-400'}`}>
                {p.active ? '● Active' : '○ Inactive'}
              </span>
              <a
                href={`https://dashboard.stripe.com/products/${p.stripeProductId}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-xs text-violet-600 flex items-center gap-1 hover:underline"
              >
                Stripe <ExternalLink className="w-3 h-3" />
              </a>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
