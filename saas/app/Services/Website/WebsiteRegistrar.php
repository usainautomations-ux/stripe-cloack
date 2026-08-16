<?php

namespace App\Services\Website;

use App\Models\SetupToken;
use App\Models\Website;
use App\Models\WebsiteCredential;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Onboarding: turning a setup token into a verified, transacting website.
 *
 * The sequence is deliberately two-legged, because neither party can prove
 * anything on its own in a single round trip:
 *
 *   register()  the plugin presents a setup token and receives credentials.
 *               At this point we know somebody with dashboard access wanted a
 *               site added — but not that they control the domain they named.
 *
 *   verify()    we call the site back and ask it to sign a nonce with the
 *               secret we just issued. A correct answer proves the plugin is
 *               genuinely installed on that domain, because only something
 *               served from that host could have received the secret.
 *
 * Without the second leg, an employee could register a competitor's domain and
 * route its payments through their own Stripe account.
 */
class WebsiteRegistrar
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    /**
     * Redeem a setup token and issue credentials for the claimed domain.
     *
     * @return array{website: Website, key_id: string, secret: string}
     */
    public function register(string $plaintextToken, string $siteUrl, string $challengeUrl, array $environment = []): array
    {
        $domain = Website::normalizeDomain($siteUrl);

        // The token is the only thing authenticating this call, so it is looked
        // up across tenants — then immediately pins the tenant for everything after.
        $token = $this->context->withoutScope(
            fn () => SetupToken::where('token_hash', SetupToken::hash($plaintextToken))->first()
        );

        if (! $token || ! $token->isRedeemable()) {
            throw new RuntimeException('This setup token is invalid, already used, or expired.');
        }

        if ($token->intended_domain && $token->intended_domain !== $domain) {
            throw new RuntimeException(
                "This token was issued for {$token->intended_domain}, but the request came from {$domain}."
            );
        }

        $this->guardChallengeUrl($challengeUrl, $domain);

        $this->context->setTenant($token->tenant_id);

        return DB::transaction(function () use ($token, $domain, $challengeUrl, $environment) {
            // Re-read inside the transaction and lock, so two plugins racing with
            // the same token cannot both be issued credentials.
            $lockedToken = SetupToken::whereKey($token->id)->lockForUpdate()->first();

            if (! $lockedToken->isRedeemable()) {
                throw new RuntimeException('This setup token has already been used.');
            }

            $existing = Website::where('domain', $domain)->first();

            if ($existing && $existing->status === 'verified') {
                throw new RuntimeException('This domain is already connected to the platform.');
            }

            $website = $existing ?? new Website(['domain' => $domain]);

            $website->fill([
                'tenant_id' => $lockedToken->tenant_id,
                'owner_user_id' => $lockedToken->created_by_user_id,
                'status' => 'pending',
                'challenge_url' => $challengeUrl,
                'plugin_version' => $environment['plugin_version'] ?? null,
                'wp_version' => $environment['wp_version'] ?? null,
                'php_version' => $environment['php_version'] ?? null,
            ])->save();

            // Re-registration invalidates whatever the site held before, so a
            // credential leaked from an old install stops working.
            $website->credentials()->where('status', 'active')->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            $issued = WebsiteCredential::issueFor($website);

            $lockedToken->forceFill([
                'used_at' => now(),
                'claimed_website_id' => $website->id,
            ])->save();

            return [
                'website' => $website,
                'key_id' => $issued['key_id'],
                'secret' => $issued['secret'],
            ];
        });
    }

    /**
     * Call the site back and require it to sign a nonce with its issued secret.
     */
    public function verify(Website $website): bool
    {
        $credential = $website->credentials()->where('status', 'active')->first();

        if (! $credential || ! $website->challenge_url) {
            return false;
        }

        $this->guardChallengeUrl($website->challenge_url, $website->domain);

        $nonce = bin2hex(random_bytes(32));

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withoutRedirecting() // A redirect could walk us off the claimed domain.
            ->acceptJson()
            ->post($website->challenge_url, ['nonce' => $nonce]);

        if (! $response->successful()) {
            return false;
        }

        $answer = (string) $response->json('signature');
        $expected = hash_hmac('sha256', $nonce, $credential->secret());

        if (! hash_equals($expected, $answer)) {
            return false;
        }

        $website->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Reject challenge URLs that do not belong to the claimed domain, or that
     * point somewhere the platform should never be made to call.
     *
     * This is the SSRF boundary. The plugin supplies this URL, so without these
     * checks a caller could make the platform issue authenticated requests to
     * cloud metadata endpoints or to hosts inside our own network.
     */
    private function guardChallengeUrl(string $url, string $domain): void
    {
        $parts = parse_url($url);

        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('The challenge URL is not a valid absolute URL.');
        }

        if (strtolower($parts['scheme']) !== 'https' && ! app()->environment('local', 'testing')) {
            throw new RuntimeException('The challenge URL must use HTTPS.');
        }

        $host = strtolower($parts['host']);

        // The callback must land on the domain being claimed. Anything else is
        // either a misconfiguration or an attempt to prove ownership of a
        // domain via a host the caller happens to control.
        if ($host !== $domain && ! str_ends_with($host, '.' . $domain)) {
            throw new RuntimeException('The challenge URL must be on the domain being registered.');
        }

        if (app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->resolveHost($host) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('The challenge URL resolves to a non-public address.');
            }
        }
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $addresses = array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        )));

        if ($addresses === []) {
            throw new RuntimeException('The challenge URL host does not resolve.');
        }

        return $addresses;
    }
}
