'use client'

import { useEffect, useState } from 'react'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'
import {
  DollarSign, Users, ShoppingCart, TrendingUp,
  Globe, Zap, AlertCircle, RefreshCw
} from 'lucide-react'

interface DashboardStats {
  revenue: { total: number; monthly: number; growth: number }
  orders: { total: number; completed: number; failed: number; pending: number }
  customers: { total: number }
  subscriptions: { active: number; mrr: number }
  tenants: { total: number; active: number }
  revenueChart: Array<{ month: string; revenue: number; orders: number }>
  topTenants: Array<{ name: string; revenue: number; orders: number }>
}

const MOCK: DashboardStats = {
  revenue: { total: 284750, monthly: 48320, growth: 23.5 },
  orders: { total: 1847, completed: 1624, failed: 89, pending: 134 },
  customers: { total: 932 },
  subscriptions: { active: 412, mrr: 28940 },
  tenants: { total: 10, active: 10 },
  revenueChart: [
    { month: 'Jun', revenue: 18200, orders: 142 },
    { month: 'Jul', revenue: 22400, orders: 178 },
    { month: 'Aug', revenue: 19800, orders: 157 },
    { month: 'Sep', revenue: 28600, orders: 229 },
    { month: 'Oct', revenue: 31200, orders: 248 },
    { month: 'Nov', revenue: 35400, orders: 282 },
    { month: 'Dec', revenue: 38900, orders: 310 },
    { month: 'Jan', revenue: 41200, orders: 329 },
    { month: 'Feb', revenue: 38100, orders: 304 },
    { month: 'Mar', revenue: 44600, orders: 356 },
    { month: 'Apr', revenue: 46200, orders: 369 },
    { month: 'May', revenue: 48320, orders: 386 },
  ],
  topTenants: [
    { name: 'TechNova SaaS', revenue: 84200, orders: 387 },
    { name: 'Digital Courses Hub', revenue: 62400, orders: 189 },
    { name: 'Email Marketing Hub', revenue: 48100, orders: 312 },
    { name: 'CloudStore Pro', revenue: 38600, orders: 276 },
    { name: 'Analytics Wizard', revenue: 29300, orders: 198 },
  ],
}

const fmt = (cents: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)

export default function DashboardPage() {
  const [stats] = useState<DashboardStats>(MOCK)

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Overview</h1>
          <p className="text-sm text-gray-500 mt-1">
            {stats.tenants.active} websites connected · All data via central Stripe account
          </p>
        </div>
        <div className="flex items-center gap-2 text-xs text-green-600 bg-green-50 px-3 py-1.5 rounded-full border border-green-200">
          <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
          Live
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          icon={<DollarSign className="w-5 h-5 text-violet-600" />}
          label="Total Revenue"
          value={fmt(stats.revenue.total)}
          sub={`${fmt(stats.revenue.monthly)}/mo`}
          badge={`+${stats.revenue.growth}%`}
          badgeColor="green"
          bg="violet"
        />
        <StatCard
          icon={<ShoppingCart className="w-5 h-5 text-blue-600" />}
          label="Total Orders"
          value={stats.orders.total.toLocaleString()}
          sub={`${stats.orders.failed} failed`}
          badge={`${Math.round((stats.orders.completed / stats.orders.total) * 100)}% success`}
          badgeColor="blue"
          bg="blue"
        />
        <StatCard
          icon={<Users className="w-5 h-5 text-teal-600" />}
          label="Customers"
          value={stats.customers.total.toLocaleString()}
          sub={`${stats.subscriptions.active} subscribed`}
          badge="Active"
          badgeColor="teal"
          bg="teal"
        />
        <StatCard
          icon={<TrendingUp className="w-5 h-5 text-amber-600" />}
          label="MRR"
          value={fmt(stats.subscriptions.mrr)}
          sub={`${stats.subscriptions.active} active subs`}
          badge="Recurring"
          badgeColor="amber"
          bg="amber"
        />
      </div>

      {/* Revenue Chart */}
      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-base font-semibold text-gray-900">Revenue (12 months)</h2>
          <span className="text-sm text-gray-500">All websites combined</span>
        </div>
        <ResponsiveContainer width="100%" height={240}>
          <AreaChart data={stats.revenueChart} margin={{ top: 4, right: 0, left: 0, bottom: 0 }}>
            <defs>
              <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#7c3aed" stopOpacity={0.15} />
                <stop offset="95%" stopColor="#7c3aed" stopOpacity={0} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="month" tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
            <YAxis
              tick={{ fontSize: 12, fill: '#9ca3af' }}
              axisLine={false}
              tickLine={false}
              tickFormatter={(v) => `$${(v / 100).toLocaleString()}`}
            />
            <Tooltip
              formatter={(v: number) => [fmt(v), 'Revenue']}
              contentStyle={{ border: '1px solid #e5e7eb', borderRadius: 8, fontSize: 13 }}
            />
            <Area
              type="monotone"
              dataKey="revenue"
              stroke="#7c3aed"
              strokeWidth={2}
              fill="url(#revGrad)"
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>

      {/* Bottom row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top Websites */}
        <div className="bg-white border border-gray-200 rounded-xl p-6">
          <h2 className="text-base font-semibold text-gray-900 mb-4">Top Websites by Revenue</h2>
          <div className="space-y-3">
            {stats.topTenants.map((t, i) => {
              const pct = Math.round((t.revenue / stats.topTenants[0].revenue) * 100)
              return (
                <div key={t.name}>
                  <div className="flex items-center justify-between mb-1">
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-gray-400 w-4">{i + 1}</span>
                      <Globe className="w-3.5 h-3.5 text-gray-400" />
                      <span className="text-sm font-medium text-gray-800">{t.name}</span>
                    </div>
                    <span className="text-sm text-gray-600">{fmt(t.revenue)}</span>
                  </div>
                  <div className="h-1.5 bg-gray-100 rounded-full ml-6">
                    <div
                      className="h-1.5 bg-violet-500 rounded-full transition-all"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </div>
              )
            })}
          </div>
        </div>

        {/* Order Breakdown */}
        <div className="bg-white border border-gray-200 rounded-xl p-6">
          <h2 className="text-base font-semibold text-gray-900 mb-4">Order Status Breakdown</h2>
          <div className="space-y-3">
            {[
              { label: 'Completed', value: stats.orders.completed, color: 'bg-green-500', pct: Math.round((stats.orders.completed / stats.orders.total) * 100) },
              { label: 'Pending', value: stats.orders.pending, color: 'bg-amber-400', pct: Math.round((stats.orders.pending / stats.orders.total) * 100) },
              { label: 'Failed', value: stats.orders.failed, color: 'bg-red-400', pct: Math.round((stats.orders.failed / stats.orders.total) * 100) },
            ].map((s) => (
              <div key={s.label}>
                <div className="flex items-center justify-between mb-1">
                  <div className="flex items-center gap-2">
                    <span className={`w-2 h-2 rounded-full ${s.color}`} />
                    <span className="text-sm text-gray-700">{s.label}</span>
                  </div>
                  <span className="text-sm text-gray-600">
                    {s.value.toLocaleString()} <span className="text-gray-400">({s.pct}%)</span>
                  </span>
                </div>
                <div className="h-1.5 bg-gray-100 rounded-full">
                  <div className={`h-1.5 ${s.color} rounded-full`} style={{ width: `${s.pct}%` }} />
                </div>
              </div>
            ))}
          </div>

          <div className="mt-6 pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
            <div className="text-center">
              <p className="text-2xl font-semibold text-gray-900">{fmt(stats.subscriptions.mrr)}</p>
              <p className="text-xs text-gray-500 mt-0.5">Monthly Recurring Revenue</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-semibold text-gray-900">{stats.subscriptions.active}</p>
              <p className="text-xs text-gray-500 mt-0.5">Active Subscriptions</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function StatCard({
  icon, label, value, sub, badge, badgeColor, bg
}: {
  icon: React.ReactNode
  label: string
  value: string
  sub: string
  badge: string
  badgeColor: string
  bg: string
}) {
  const bgMap: Record<string, string> = {
    violet: 'bg-violet-50', blue: 'bg-blue-50',
    teal: 'bg-teal-50', amber: 'bg-amber-50'
  }
  const badgeMap: Record<string, string> = {
    green: 'bg-green-100 text-green-700',
    blue: 'bg-blue-100 text-blue-700',
    teal: 'bg-teal-100 text-teal-700',
    amber: 'bg-amber-100 text-amber-700',
  }
  return (
    <div className="bg-white border border-gray-200 rounded-xl p-5">
      <div className="flex items-start justify-between">
        <div className={`p-2 rounded-lg ${bgMap[bg]}`}>{icon}</div>
        <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${badgeMap[badgeColor]}`}>
          {badge}
        </span>
      </div>
      <p className="mt-3 text-2xl font-semibold text-gray-900">{value}</p>
      <p className="text-sm text-gray-500 mt-0.5">{label}</p>
      <p className="text-xs text-gray-400 mt-1">{sub}</p>
    </div>
  )
}
