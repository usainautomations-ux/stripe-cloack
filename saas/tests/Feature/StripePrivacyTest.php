<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StripeAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Services\Stripe\StripePayloadBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards the platform's defining requirement: Stripe sees the platform, never
 * the client site the payment originated from.
 *
 * This is the requirement most likely to be broken silently by a future change,
 * because breaking it costs nothing at runtime — payments still succeed, and
 * the leak is only visible to someone reading the Stripe dashboard weeks later.
 */
class StripePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_DOMAIN = 'secret-client-shop.com';

    private Order $order;

    private StripeAccount $account;

    private StripePayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->builder = app(StripePayloadBuilder::class);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'role_key' => 'tenant_owner',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->account = new StripeAccount([
            'tenant_id' => $tenant->id,
            'label' => 'Acme Live',
            'mode' => 'test',
            'publishable_key' => 'pk_test_abc',
            'statement_descriptor' => 'USMAN.COM',
        ]);
        $this->account->setSecretKey('sk_test_abcdef123456');
        $this->account->save();

        $website = Website::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'domain' => self::CLIENT_DOMAIN,
            'display_name' => 'Secret Client Shop',
            'status' => 'verified',
            'stripe_account_id' => $this->account->id,
        ]);

        $this->order = Order::create([
            'tenant_id' => $tenant->id,
            'website_id' => $website->id,
            'stripe_account_id' => $this->account->id,
            'order_ref' => 'SHOP-1001',
            'amount_minor' => 4999,
            'currency' => 'usd',
            'status' => 'pending',
        ]);
    }

    public function test_the_payment_intent_payload_contains_no_trace_of_the_client_domain(): void
    {
        $params = $this->builder->paymentIntentParams($this->order, $this->account);

        $this->assertStringNotContainsStringIgnoringCase(
            self::CLIENT_DOMAIN,
            json_encode($params),
            'The PaymentIntent payload leaks the originating website domain to Stripe.'
        );

        // The shop's human-readable name is the domain by another route.
        $this->assertStringNotContainsStringIgnoringCase(
            'Secret Client Shop',
            json_encode($params),
            'The PaymentIntent payload leaks the originating website name to Stripe.'
        );
    }

    public function test_the_description_is_built_from_the_platform_not_the_website(): void
    {
        $description = $this->builder->descriptionFor($this->order, $this->account);

        $this->assertStringContainsString('USMAN.COM', $description);
        $this->assertStringNotContainsStringIgnoringCase(self::CLIENT_DOMAIN, $description);
    }

    public function test_the_site_reference_is_salted_so_it_cannot_be_reversed_to_a_domain(): void
    {
        $reference = $this->builder->siteReference($this->order);

        // An unsalted hash of a domain is trivially reversible — the space of
        // plausible domains is small enough to brute-force from the Stripe
        // dashboard alone. These are the naive constructions that must NOT match.
        $this->assertNotSame('site_' . substr(hash('sha256', self::CLIENT_DOMAIN), 0, 16), $reference);
        $this->assertNotSame('site_' . substr(md5(self::CLIENT_DOMAIN), 0, 16), $reference);
        $this->assertNotSame('site_' . substr(hash('sha256', $this->order->website_id), 0, 16), $reference);

        // Still stable, so reconciliation works.
        $this->assertSame($reference, $this->builder->siteReference($this->order));
    }

    public function test_the_leak_guard_rejects_a_payload_containing_the_domain(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('originating website domain');

        // Simulates a future change that innocently forwards the site's own
        // description straight through to Stripe.
        $this->builder->assertNoDomainLeak(
            [
                'amount' => 4999,
                'description' => 'Order from ' . self::CLIENT_DOMAIN,
            ],
            self::CLIENT_DOMAIN
        );
    }

    public function test_the_leak_guard_catches_a_domain_hidden_in_nested_metadata(): void
    {
        $this->expectException(RuntimeException::class);

        $this->builder->assertNoDomainLeak(
            ['metadata' => ['source' => ['referrer' => 'https://' . self::CLIENT_DOMAIN . '/cart']]],
            self::CLIENT_DOMAIN
        );
    }

    public function test_the_leak_guard_ignores_a_www_prefix_difference(): void
    {
        $this->expectException(RuntimeException::class);

        // The stored domain carries www., the leak does not. Comparing them
        // naively would let this through.
        $this->builder->assertNoDomainLeak(
            ['description' => 'Order from ' . self::CLIENT_DOMAIN],
            'www.' . self::CLIENT_DOMAIN
        );
    }

    public function test_a_clean_payload_passes_the_guard(): void
    {
        $params = $this->builder->paymentIntentParams($this->order, $this->account);

        $this->builder->assertNoDomainLeak($params, self::CLIENT_DOMAIN);

        $this->assertTrue(true, 'A payload built by the builder should always pass its own guard.');
    }

    public function test_metadata_carries_only_opaque_identifiers(): void
    {
        $metadata = $this->builder->metadataFor($this->order);

        $this->assertSame($this->order->id, $metadata['platform_order_id']);
        $this->assertStringStartsWith('site_', $metadata['platform_site_ref']);

        // The tenant reference is a digest, not the tenant's own id, so a
        // Stripe-side observer cannot correlate accounts back to our records.
        $this->assertNotSame($this->order->tenant_id, $metadata['platform_tenant_ref']);
    }
}
