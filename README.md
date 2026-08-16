# Usman Pay Platform

A multi-tenant payment platform. WordPress sites install a plugin, connect to
the platform, and route payments through Stripe accounts the platform holds
credentials for — so Stripe sees the platform, never the originating client site.

```
clientwebsite.com  →  plugin  →  usman.com API  →  assigned Stripe account  →  Stripe
```

## Repository layout

| Path | What it is |
|------|-----------|
| `saas/` | Laravel backend, hosted checkout, plugin API, webhook ingestion |
| `wordpress-plugin/usman-pay/` | The WordPress plugin installed on client sites |
| `scripts/` | Local development helpers |
| `docs/` | Architecture documentation |

## What is built

The security-critical spine is complete and tested end to end:

- **Tenant isolation** — application scoping *and* PostgreSQL row-level security,
  default-deny. Proven by tests that use raw SQL to bypass Eloquent entirely.
- **Secret storage** — envelope encryption for Stripe keys and plugin secrets.
  A stolen database dump is not enough to recover a usable Stripe key.
- **Plugin authentication** — HMAC-SHA256 request signing with replay protection
  and clock-skew bounds. Tested by loading the plugin's *real* signer into the
  Laravel suite, so the two codebases cannot silently drift apart.
- **Website onboarding** — single-use setup tokens plus a domain-ownership
  callback, with SSRF guarding on the callback URL.
- **Payment creation** — Stripe PaymentIntents with idempotency on both sides.
- **Stripe privacy** — a dedicated payload builder, and a guard that refuses to
  transmit any payload containing the originating domain.
- **Webhook ingestion** — signature verification, deduplication by event id,
  asynchronous processing.
- **Hosted checkout** — served from the platform domain, which is the mechanism
  that keeps the client domain out of Stripe's view.

## What is not built yet

- Dashboard UI (admin, tenant, and employee screens)
- Dashboard authentication and MFA enrolment flows
- Refund and dispute *operations* (the data model and webhook reconciliation
  exist; the authorized user-facing action does not)
- Reporting and exports
- KMS key provider (the interface exists; only the local provider is implemented)
- Production deployment configuration

## Local setup

Prerequisites are already installed on this machine at `C:\Users\mania\tools\`:
PHP 8.3.33, Composer 2.10, PostgreSQL 16.14 (portable build).

**1. Start PostgreSQL** — it is not a Windows service, so it needs starting after
each reboot:

```bash
powershell -File scripts/start-postgres.ps1
```

**2. Install dependencies and migrate:**

```bash
cd saas && composer install && php artisan migrate --seed
```

**3. Run the test suite:**

```bash
cd saas && php artisan test
```

**4. Serve the application:**

```bash
cd saas && php artisan serve
```

### Database roles

Laravel connects as `usman_app`, which is deliberately **not** a superuser and
carries `NOBYPASSRLS`. This matters: a superuser silently bypasses every
row-level security policy, which would make the entire isolation layer inert
while all its tests still passed.

Tests run against a separate `usman_saas_test` database — on PostgreSQL, not
SQLite, because SQLite has no row-level security and the isolation suite would
report green while proving nothing.

## Configuration

Secrets live in `saas/.env` (gitignored). Two matter most:

- `APP_KEY` — Laravel's own encryption key.
- `SECRETS_MASTER_KEY` — wraps every stored Stripe key and plugin secret.
  **Back this up.** Losing it makes every stored secret unrecoverable.

Generate the latter with:

```bash
php artisan secrets:generate-key
```

## Connecting a WordPress site

1. Generate a setup token in the dashboard (token is single-use, valid 15 minutes).
2. Install and activate the `usman-pay` plugin on the client site.
3. Paste the token into **Settings → Usman Pay** and press Connect.
4. The plugin exchanges the token for credentials, then the platform calls the
   site back and requires it to sign a nonce — proving the plugin really is
   installed on the domain being claimed.
5. An administrator assigns a Stripe account to the site. Until then the site is
   connected but cannot take payments.

The plugin never stores, receives, or transmits a Stripe key. There is no field
for one anywhere in it, by design.
