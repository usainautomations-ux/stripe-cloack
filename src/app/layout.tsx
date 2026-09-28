import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Stripe Gateway Hub',
  description: 'Central payment hub — one Stripe account powering multiple websites',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  )
}
