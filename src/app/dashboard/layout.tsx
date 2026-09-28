import Link from 'next/link'
import {
  LayoutDashboard, Globe, Package, ShoppingCart, Users,
  CreditCard, BarChart3, Webhook, Key, Settings, Zap
} from 'lucide-react'

const NAV = [
  { href: '/dashboard', icon: LayoutDashboard, label: 'Overview' },
  { href: '/dashboard/tenants', icon: Globe, label: 'Websites' },
  { href: '/dashboard/products', icon: Package, label: 'Products' },
  { href: '/dashboard/orders', icon: ShoppingCart, label: 'Orders' },
  { href: '/dashboard/customers', icon: Users, label: 'Customers' },
  { href: '/dashboard/subscriptions', icon: CreditCard, label: 'Subscriptions' },
  { href: '/dashboard/analytics', icon: BarChart3, label: 'Analytics' },
  { href: '/dashboard/webhooks', icon: Webhook, label: 'Webhooks' },
  { href: '/dashboard/keys', icon: Key, label: 'API Keys' },
  { href: '/dashboard/settings', icon: Settings, label: 'Settings' },
]

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <aside className="w-60 bg-white border-r border-gray-200 flex flex-col">
        {/* Logo */}
        <div className="px-5 py-5 border-b border-gray-100">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
              <Zap className="w-4 h-4 text-white" />
            </div>
            <div>
              <p className="text-sm font-semibold text-gray-900">Gateway Hub</p>
              <p className="text-xs text-gray-400">Stripe Central</p>
            </div>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
          {NAV.map(({ href, icon: Icon, label }) => (
            <Link
              key={href}
              href={href}
              className="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors group"
            >
              <Icon className="w-4 h-4 text-gray-400 group-hover:text-gray-600" />
              {label}
            </Link>
          ))}
        </nav>

        {/* Footer */}
        <div className="px-4 py-4 border-t border-gray-100">
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-full bg-violet-100 flex items-center justify-center text-xs font-semibold text-violet-700">
              A
            </div>
            <div>
              <p className="text-xs font-medium text-gray-800">Admin</p>
              <p className="text-xs text-gray-400">admin@hub.com</p>
            </div>
          </div>
        </div>
      </aside>

      {/* Main */}
      <main className="flex-1 overflow-y-auto">
        {children}
      </main>
    </div>
  )
}
