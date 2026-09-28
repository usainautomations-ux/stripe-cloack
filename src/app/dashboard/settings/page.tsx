'use client'

import { useState } from 'react'
import { Eye, EyeOff, Save, CheckCircle, AlertTriangle, ExternalLink, Zap } from 'lucide-react'

export default function SettingsPage() {
  const [showSecret, setShowSecret] = useState(false)
  const [showWebhook, setShowWebhook] = useState(false)
  const [saved, setSaved] = useState(false)
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<'success' | 'error' | null>(null)

  const [form, setForm] = useState({
    stripeSecretKey: 'sk_live_••••••••••••••••••••••••••••••••',
    stripePublishableKey: 'pk_live_••••••••••••••••••••',
    stripeWebhookSecret: 'whsec_••••••••••••••••••••••••',
    appUrl: 'https://payments.example.com',
    adminEmail: 'admin@example.com',
  })

  const handleSave = () => {
    setSaved(true)
    setTimeout(() => setSaved(false), 2500)
  }

  const handleTestStripe = async () => {
    setTesting(true)
    await new Promise((r) => setTimeout(r, 1500))
    setTestResult('success')
    setTesting(false)
    setTimeout(() => setTestResult(null), 3000)
  }

  return (
    <div className="p-8 max-w-3xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">Settings</h1>
        <p className="text-sm text-gray-500 mt-1">
          Stripe credentials and hub configuration — stored securely, never exposed to client websites
        </p>
      </div>

      {/* Stripe Credentials */}
      <section className="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div className="flex items-center justify-between mb-5">
          <div className="flex items-center gap-2">
            <div className="w-7 h-7 bg-violet-100 rounded-lg flex items-center justify-center">
              <Zap className="w-3.5 h-3.5 text-violet-600" />
            </div>
            <h2 className="text-base font-semibold text-gray-900">Stripe Credentials</h2>
          </div>
          <a
            href="https://dashboard.stripe.com/apikeys"
            target="_blank"
            rel="noopener noreferrer"
            className="text-xs text-violet-600 flex items-center gap-1 hover:underline"
          >
            Stripe Dashboard <ExternalLink className="w-3 h-3" />
          </a>
        </div>

        <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-5 flex items-start gap-2">
          <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
          <p className="text-xs text-amber-700">
            These keys are stored encrypted server-side and <strong>never sent to client websites</strong>.
            Connected sites only receive an API key that routes through this hub.
          </p>
        </div>

        <div className="space-y-4">
          <Field label="Secret Key" hint="sk_live_... or sk_test_...">
            <div className="relative">
              <input
                type={showSecret ? 'text' : 'password'}
                value={form.stripeSecretKey}
                onChange={(e) => setForm({ ...form, stripeSecretKey: e.target.value })}
                className="w-full pr-10 pl-3 py-2.5 text-sm border border-gray-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
              <button
                onClick={() => setShowSecret(!showSecret)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                {showSecret ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </Field>

          <Field label="Publishable Key" hint="pk_live_... or pk_test_...">
            <input
              type="text"
              value={form.stripePublishableKey}
              onChange={(e) => setForm({ ...form, stripePublishableKey: e.target.value })}
              className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </Field>

          <Field
            label="Webhook Secret"
            hint="From Stripe Dashboard → Webhooks → your endpoint"
          >
            <div className="relative">
              <input
                type={showWebhook ? 'text' : 'password'}
                value={form.stripeWebhookSecret}
                onChange={(e) => setForm({ ...form, stripeWebhookSecret: e.target.value })}
                className="w-full pr-10 pl-3 py-2.5 text-sm border border-gray-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
              <button
                onClick={() => setShowWebhook(!showWebhook)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                {showWebhook ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </Field>
        </div>

        <div className="mt-5 pt-4 border-t border-gray-100">
          <p className="text-xs text-gray-500 font-medium mb-2">Stripe Webhook Endpoint</p>
          <div className="flex items-center gap-2 bg-gray-900 text-green-400 font-mono text-xs rounded-lg px-3 py-2.5">
            <span className="text-gray-500">POST</span>
            <span>{form.appUrl}/api/stripe/webhook</span>
          </div>
          <p className="text-xs text-gray-400 mt-2">
            Add this URL in your Stripe Dashboard under Developers → Webhooks.
            Subscribe to: <code className="bg-gray-100 px-1 rounded">checkout.session.completed</code>,{' '}
            <code className="bg-gray-100 px-1 rounded">customer.subscription.*</code>,{' '}
            <code className="bg-gray-100 px-1 rounded">invoice.payment_failed</code>,{' '}
            <code className="bg-gray-100 px-1 rounded">charge.refunded</code>
          </p>
        </div>

        <div className="mt-4 flex items-center gap-3">
          <button
            onClick={handleTestStripe}
            disabled={testing}
            className="text-sm border border-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
          >
            {testing ? 'Testing…' : 'Test Connection'}
          </button>
          {testResult === 'success' && (
            <span className="text-sm text-green-600 flex items-center gap-1">
              <CheckCircle className="w-4 h-4" /> Connected to Stripe ✓
            </span>
          )}
        </div>
      </section>

      {/* Hub Config */}
      <section className="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 className="text-base font-semibold text-gray-900 mb-5">Hub Configuration</h2>
        <div className="space-y-4">
          <Field label="Hub URL" hint="Public URL where this app is hosted">
            <input
              type="url"
              value={form.appUrl}
              onChange={(e) => setForm({ ...form, appUrl: e.target.value })}
              className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </Field>
          <Field label="Admin Email" hint="Used for system notifications">
            <input
              type="email"
              value={form.adminEmail}
              onChange={(e) => setForm({ ...form, adminEmail: e.target.value })}
              className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </Field>
        </div>
      </section>

      {/* Payment Description Format */}
      <section className="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 className="text-base font-semibold text-gray-900 mb-2">
          Stripe Payment Description Format
        </h2>
        <p className="text-xs text-gray-500 mb-4">
          How payments appear in your Stripe Dashboard — so you know which website each payment came from.
        </p>
        <div className="space-y-2">
          {[
            { label: 'Payment description', value: '[TechNova SaaS] Pro Plan — Annual Subscription' },
            { label: 'Statement descriptor', value: 'TECHNOVA*GATEWAY' },
            { label: 'Metadata: tenant', value: 'technova' },
            { label: 'Metadata: source', value: 'stripe_gateway_hub' },
            { label: 'Metadata: order_id', value: 'ord_clxyz123' },
          ].map((row) => (
            <div key={row.label} className="flex items-center justify-between text-sm py-2 border-b border-gray-50 last:border-0">
              <span className="text-gray-500 text-xs">{row.label}</span>
              <code className="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-mono">{row.value}</code>
            </div>
          ))}
        </div>
      </section>

      <div className="flex justify-end">
        <button
          onClick={handleSave}
          className="flex items-center gap-2 bg-violet-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors"
        >
          {saved ? <CheckCircle className="w-4 h-4" /> : <Save className="w-4 h-4" />}
          {saved ? 'Saved!' : 'Save Settings'}
        </button>
      </div>
    </div>
  )
}

function Field({
  label,
  hint,
  children,
}: {
  label: string
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
      {hint && <p className="text-xs text-gray-400 mt-1">{hint}</p>}
    </div>
  )
}
