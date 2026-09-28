import { PrismaClient } from '@prisma/client'
import crypto from 'crypto'

const prisma = new PrismaClient()

const API_KEY_PREFIX = 'sgw_'

// Must match the secret the app uses in src/lib/auth.ts, or seeded keys won't validate
if (!process.env.API_KEY_SECRET) {
  throw new Error('API_KEY_SECRET is not set')
}
const API_KEY_SECRET: string = process.env.API_KEY_SECRET

function generateApiKey() {
  const raw = crypto.randomBytes(32).toString('hex')
  const key = `${API_KEY_PREFIX}${raw}`
  const prefix = key.slice(0, 12)
  const hash = crypto
    .createHmac('sha256', API_KEY_SECRET)
    .update(key)
    .digest('hex')
  return { key, prefix, hash }
}

const TENANTS = [
  {
    name: 'TechNova SaaS',
    slug: 'technova',
    domain: 'technova.io',
    products: [
      { name: 'Starter Plan', amount: 2900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_technova_starter', stripePriceId: 'price_technova_starter' },
      { name: 'Pro Plan', amount: 9900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_technova_pro', stripePriceId: 'price_technova_pro' },
    ],
  },
  {
    name: 'Digital Courses Hub',
    slug: 'digitalcourses',
    domain: 'digitalcourses.com',
    products: [
      { name: 'React Mastery Course', amount: 19700, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_dc_react', stripePriceId: 'price_dc_react' },
      { name: 'Full Stack Bootcamp', amount: 49700, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_dc_fullstack', stripePriceId: 'price_dc_fullstack' },
    ],
  },
  {
    name: 'CloudStore Pro',
    slug: 'cloudstore',
    domain: 'cloudstore.pro',
    products: [
      { name: '100GB Storage', amount: 499, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_cs_100gb', stripePriceId: 'price_cs_100gb' },
      { name: '1TB Storage', amount: 1999, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_cs_1tb', stripePriceId: 'price_cs_1tb' },
    ],
  },
  {
    name: 'Analytics Wizard',
    slug: 'analyticswizard',
    domain: 'analyticswizard.app',
    products: [
      { name: 'Business Analytics', amount: 4900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_aw_biz', stripePriceId: 'price_aw_biz' },
    ],
  },
  {
    name: 'Design Assets Co',
    slug: 'designassets',
    domain: 'designassets.co',
    products: [
      { name: 'Icon Pack Premium', amount: 3900, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_da_icons', stripePriceId: 'price_da_icons' },
      { name: 'Template Bundle', amount: 7900, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_da_templates', stripePriceId: 'price_da_templates' },
    ],
  },
  {
    name: 'FitTrack App',
    slug: 'fittrack',
    domain: 'fittrack.health',
    products: [
      { name: 'Monthly Premium', amount: 1499, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_ft_monthly', stripePriceId: 'price_ft_monthly' },
      { name: 'Annual Premium', amount: 9900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'year', stripeProductId: 'prod_ft_annual', stripePriceId: 'price_ft_annual' },
    ],
  },
  {
    name: 'LegalDocs Online',
    slug: 'legaldocs',
    domain: 'legaldocs.online',
    products: [
      { name: 'Single Document', amount: 2900, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_ld_single', stripePriceId: 'price_ld_single' },
      { name: 'Unlimited Access', amount: 5900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_ld_unlimited', stripePriceId: 'price_ld_unlimited' },
    ],
  },
  {
    name: 'Email Marketing Hub',
    slug: 'emailhub',
    domain: 'emailhub.io',
    products: [
      { name: 'Up to 5,000 subscribers', amount: 1900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_eh_5k', stripePriceId: 'price_eh_5k' },
      { name: 'Up to 25,000 subscribers', amount: 5900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_eh_25k', stripePriceId: 'price_eh_25k' },
    ],
  },
  {
    name: 'Resume Builder AI',
    slug: 'resumebuilder',
    domain: 'resumebuilder.ai',
    products: [
      { name: 'Resume Download', amount: 990, currency: 'usd', billingType: 'ONE_TIME', stripeProductId: 'prod_rb_single', stripePriceId: 'price_rb_single' },
      { name: 'Pro Access', amount: 1490, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_rb_pro', stripePriceId: 'price_rb_pro' },
    ],
  },
  {
    name: 'API Gateway Services',
    slug: 'apigateway',
    domain: 'apigateway.services',
    products: [
      { name: '10,000 API calls/mo', amount: 2900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_ag_10k', stripePriceId: 'price_ag_10k' },
      { name: '100,000 API calls/mo', amount: 9900, currency: 'usd', billingType: 'SUBSCRIPTION', interval: 'month', stripeProductId: 'prod_ag_100k', stripePriceId: 'price_ag_100k' },
    ],
  },
]

async function main() {
  console.log('🌱 Seeding Stripe Gateway Hub database...\n')

  const createdKeys: Array<{ name: string; slug: string; apiKey: string }> = []

  for (const tenantData of TENANTS) {
    const { key, prefix, hash } = generateApiKey()

    const tenant = await prisma.tenant.upsert({
      where: { slug: tenantData.slug },
      update: {},
      create: {
        name: tenantData.name,
        slug: tenantData.slug,
        domain: tenantData.domain,
        apiKeyHash: hash,
        apiKeyPrefix: prefix,
        active: true,
        branding: {
          primaryColor: '#635bff',
          logoUrl: null,
        },
      },
    })

    // Create products for this tenant
    for (const productData of tenantData.products) {
      await prisma.product.upsert({
        where: { id: `${tenant.id}_${productData.stripePriceId}` },
        update: {},
        create: {
          id: `${tenant.id}_${productData.stripePriceId}`,
          tenantId: tenant.id,
          stripeProductId: productData.stripeProductId,
          stripePriceId: productData.stripePriceId,
          name: productData.name,
          amount: productData.amount,
          currency: productData.currency,
          billingType: productData.billingType as 'ONE_TIME' | 'SUBSCRIPTION',
          interval: productData.interval || null,
          active: true,
        },
      })
    }

    createdKeys.push({ name: tenantData.name, slug: tenantData.slug, apiKey: key })
    console.log(`✓ ${tenantData.name} (${tenantData.slug})`)
  }

  console.log('\n🔑 API Keys (SAVE THESE — not stored in plain text):\n')
  console.log('─'.repeat(80))
  for (const { name, slug, apiKey } of createdKeys) {
    console.log(`${name.padEnd(30)} ${slug.padEnd(20)} ${apiKey}`)
  }
  console.log('─'.repeat(80))
  console.log('\n✅ Seeding complete! 10 tenants created.\n')
  console.log('⚠️  Note: The Stripe Product/Price IDs above are placeholders.')
  console.log('   Replace them with real IDs from your Stripe Dashboard.\n')
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect())
