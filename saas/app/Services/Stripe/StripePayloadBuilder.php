<?php

namespace App\Services\Stripe;

use App\Models\Order;
use App\Models\StripeAccount;

/**
 * Builds everything sent to Stripe — and, more importantly, is the single place
 * that decides what is NOT sent.
 *
 * The platform requirement is that Stripe sees the platform, never the
 * originating client domain. That is easy to state and easy to violate by
 * accident, because a domain can leak through half a dozen innocuous-looking
 * fields. Centralising payload construction here means the rule is enforced in
 * one auditable place rather than re-decided at every call site.
 *
 * Deliberately NEVER sent to Stripe:
 *
 *   - the originating website's domain, in any field
 *   - the site's display name or branding
 *   - the shopper's referring URL
 *   - return_url pointing at the client domain (the platform's own domain is
 *     used instead, and the platform redirects onward afterwards)
 *   - any metadata value derived from the domain
 *
 * Sent instead: opaque platform identifiers. They are meaningless to anyone
 * reading the Stripe dashboard but let us reconcile a Stripe object back to an
 * order in our own database.
 *
 * Note the asymmetry this creates: the Stripe dashboard becomes useless for
 * telling which client site a payment came from. That is the point, and it is
 * why our own reporting has to be good — see the transaction dashboard.
 */
class StripePayloadBuilder
{
    /**
     * Metadata keys we attach. Values are all opaque ids or platform-controlled
     * strings; none is derived from the client domain.
     */
    public function metadataFor(Order $order): array
    {
        return [
            // Opaque UUIDs. Reconcilable by us, meaningless to an observer.
            'platform_order_id' => $order->id,
            'platform_site_ref' => $this->siteReference($order),
            'platform_tenant_ref' => substr(hash('sha256', $order->tenant_id), 0, 16),
            'platform' => config('app.platform_name', 'usman.com'),
        ];
    }

    /**
     * A stable pseudonym for the originating website.
     *
     * A hash rather than the domain itself, and salted with the app key so it
     * cannot be brute-forced back to a domain from the Stripe dashboard alone —
     * the domain space is small enough that an unsalted hash would be trivially
     * reversible by anyone with a wordlist.
     */
    public function siteReference(Order $order): string
    {
        return 'site_' . substr(
            hash_hmac('sha256', $order->website_id, config('app.key')),
            0,
            16
        );
    }

    /**
     * What appears on the payment in Stripe and, where applicable, on the
     * cardholder's statement.
     *
     * The descriptor is taken from the Stripe account's own configuration —
     * never from the website — so a client domain cannot reach a bank statement.
     */
    public function descriptionFor(Order $order, StripeAccount $account): string
    {
        $descriptor = $account->statement_descriptor
            ?: config('app.platform_name', 'usman.com');

        return sprintf('%s order %s', $descriptor, substr($order->id, 0, 8));
    }

    /**
     * Full PaymentIntent parameters.
     */
    public function paymentIntentParams(Order $order, StripeAccount $account): array
    {
        $params = [
            'amount' => $order->amount_minor,
            'currency' => $order->currency,
            'description' => $this->descriptionFor($order, $account),
            'metadata' => $this->metadataFor($order),
            'automatic_payment_methods' => ['enabled' => true],
        ];

        if ($account->statement_descriptor) {
            // Stripe caps this at 22 characters and rejects several symbols.
            $params['statement_descriptor_suffix'] = substr($account->statement_descriptor, 0, 22);
        }

        return $params;
    }

    /**
     * Guard against a domain reaching Stripe through a field nobody thought about.
     *
     * This runs over the final payload immediately before dispatch. It is a
     * cheap assertion of an expensive-to-violate property, and it exists because
     * the failure it catches is silent: nothing breaks, the payment succeeds,
     * and the leak is only discovered by reading the Stripe dashboard.
     *
     * @throws \RuntimeException
     */
    public function assertNoDomainLeak(array $payload, string $domain): void
    {
        $flattened = json_encode($payload);
        $bareDomain = preg_replace('/^www\./', '', strtolower($domain));

        if ($bareDomain !== '' && stripos((string) $flattened, $bareDomain) !== false) {
            throw new \RuntimeException(
                'Refusing to send this payload to Stripe: it contains the originating website domain.'
            );
        }
    }
}
