/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  experimental: {
    serverComponentsExternalPackages: ['@prisma/client', 'prisma'],
  },
  // Disable body parsing for webhook route (need raw body)
  async rewrites() {
    return []
  },
}

module.exports = nextConfig
