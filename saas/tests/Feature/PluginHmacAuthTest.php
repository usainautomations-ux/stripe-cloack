<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCredential;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies the signing contract between two separate codebases.
 *
 * This test loads the WordPress plugin's ACTUAL signer class rather than
 * reimplementing its logic. That distinction is the entire point: a test that
 * signs with a local copy of the algorithm would keep passing after the plugin
 * and the server drifted apart, which is precisely the bug worth catching —
 * every site in the field would fail to authenticate at once.
 */
class PluginHmacAuthTest extends TestCase
{
    use RefreshDatabase;

    private Website $website;

    private string $keyId;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadPluginSigner();
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'role_key' => 'tenant_owner',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->website = Website::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'domain' => 'acme-store.com',
            'status' => 'verified',
        ]);

        $issued = WebsiteCredential::issueFor($this->website);
        $this->keyId = $issued['key_id'];
        $this->secret = $issued['secret'];
    }

    /**
     * Pull in the plugin's signer. It guards on ABSPATH the way every WordPress
     * file does, so we define it to satisfy the guard.
     */
    private function loadPluginSigner(): void
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }

        if (! class_exists(\Usman_Pay_Signer::class)) {
            require_once dirname(__DIR__, 3)
                . '/wordpress-plugin/usman-pay/includes/class-usman-pay-signer.php';
        }
    }

    /**
     * Build headers exactly as the plugin's client does.
     */
    private function sign(string $method, string $path, string $body = '', array $overrides = []): array
    {
        $timestamp = (string) time();
        $nonce = \Usman_Pay_Signer::nonce();

        $canonical = \Usman_Pay_Signer::canonical($method, $path, $timestamp, $nonce, $body);
        $signature = \Usman_Pay_Signer::sign($canonical, $this->secret);

        return array_merge([
            'X-Usman-Key' => $this->keyId,
            'X-Usman-Timestamp' => $timestamp,
            'X-Usman-Nonce' => $nonce,
            'X-Usman-Signature' => $signature,
        ], $overrides);
    }

    private function handshakePath(): string
    {
        return '/v1/website/' . $this->website->id . '/handshake';
    }

    /**
     * Note: GET requests use get(), not getJson().
     *
     * getJson() serialises its (empty) data array and sends "[]" as the body,
     * whereas a real plugin GET sends no body at all and signs the hash of the
     * empty string. Signing one and sending the other fails verification — the
     * scheme working exactly as intended, but for a reason that has nothing to
     * do with the code under test.
     */
    public function test_a_request_signed_by_the_plugin_is_accepted(): void
    {
        $path = $this->handshakePath();

        $response = $this->get($path, $this->sign('GET', $path));

        $response->assertOk()->assertJson(['status' => 'verified']);
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $this->getJson($this->handshakePath())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'missing_signature_headers');
    }

    public function test_a_signature_made_with_the_wrong_secret_is_rejected(): void
    {
        $path = $this->handshakePath();
        $timestamp = (string) time();
        $nonce = \Usman_Pay_Signer::nonce();

        $forged = \Usman_Pay_Signer::sign(
            \Usman_Pay_Signer::canonical('GET', $path, $timestamp, $nonce, ''),
            'not-the-real-secret'
        );

        $this->get($path, [
            'X-Usman-Key' => $this->keyId,
            'X-Usman-Timestamp' => $timestamp,
            'X-Usman-Nonce' => $nonce,
            'X-Usman-Signature' => $forged,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_a_replayed_request_is_rejected_the_second_time(): void
    {
        $path = $this->handshakePath();
        $headers = $this->sign('GET', $path);

        $this->get($path, $headers)->assertOk();

        // Byte-identical replay: valid signature, valid timestamp, used nonce.
        $this->get($path, $headers)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'replayed_request');
    }

    public function test_a_stale_timestamp_is_rejected_even_with_a_valid_signature(): void
    {
        $path = $this->handshakePath();
        $timestamp = (string) (time() - 3600);
        $nonce = \Usman_Pay_Signer::nonce();

        // Correctly signed for that timestamp — the signature is genuine, the
        // request is simply too old to still be honoured.
        $signature = \Usman_Pay_Signer::sign(
            \Usman_Pay_Signer::canonical('GET', $path, $timestamp, $nonce, ''),
            $this->secret
        );

        $this->get($path, [
            'X-Usman-Key' => $this->keyId,
            'X-Usman-Timestamp' => $timestamp,
            'X-Usman-Nonce' => $nonce,
            'X-Usman-Signature' => $signature,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'stale_request');
    }

    public function test_tampering_with_the_body_invalidates_the_signature(): void
    {
        $path = '/v1/payment/create';
        $body = json_encode(['order_ref' => 'A-1', 'amount' => 1000, 'currency' => 'usd']);

        $headers = $this->sign('POST', $path, $body);

        // Sign 1000, send 1. The body hash is part of the canonical string, so
        // the amount cannot be altered in flight.
        $this->withHeaders($headers)->postJson($path, [
            'order_ref' => 'A-1',
            'amount' => 1,
            'currency' => 'usd',
        ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_a_revoked_credential_stops_working(): void
    {
        $this->website->credentials()->first()->revoke();

        $path = $this->handshakePath();

        $this->get($path, $this->sign('GET', $path))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_signature');
    }

    public function test_credentials_cannot_act_on_a_different_website(): void
    {
        $otherTenant = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $otherOwner = User::create([
            'tenant_id' => $otherTenant->id,
            'role_key' => 'tenant_owner',
            'name' => 'Other Owner',
            'email' => 'owner@globex.test',
            'password' => Hash::make('secret-password'),
        ]);

        $otherSite = Website::create([
            'tenant_id' => $otherTenant->id,
            'owner_user_id' => $otherOwner->id,
            'domain' => 'globex-store.com',
            'status' => 'verified',
        ]);

        // Acme's credentials, Globex's website id in the URL.
        $path = '/v1/website/' . $otherSite->id . '/handshake';

        $this->get($path, $this->sign('GET', $path))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'website_mismatch');
    }
}
