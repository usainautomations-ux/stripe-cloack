<?php

namespace App\Http\Middleware;

use App\Models\WebsiteCredential;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a request from an installed WordPress plugin.
 *
 * The canonical string is identical to the one the plugin builds (see
 * Usman_Pay_Signer). Any divergence between the two is a hard failure rather
 * than a degraded mode — there is no "unsigned but allowed" path.
 *
 * Three separate things are checked, and skipping any one of them breaks a
 * different property:
 *
 *   signature   proves the caller holds the site secret
 *   timestamp   bounds how long a captured request stays useful
 *   nonce       stops that request being replayed within those bounds
 *
 * A valid signature alone is not enough: without the nonce check, anyone who
 * observed one "create payment" call could replay it verbatim.
 */
class AuthenticatePluginRequest
{
    /**
     * Requests dated more than this far from now are rejected in either
     * direction — future-dated timestamps are just as suspicious as stale ones.
     */
    private const MAX_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = $request->header('X-Usman-Key');
        $timestamp = $request->header('X-Usman-Timestamp');
        $nonce = $request->header('X-Usman-Nonce');
        $signature = $request->header('X-Usman-Signature');

        if (! $keyId || ! $timestamp || ! $nonce || ! $signature) {
            return $this->reject('missing_signature_headers', 'Request is not signed.');
        }

        if (! ctype_digit((string) $timestamp)) {
            return $this->reject('invalid_timestamp', 'Timestamp is not a unix time.');
        }

        if (abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return $this->reject('stale_request', 'Request timestamp is outside the accepted window.');
        }

        // Resolving the credential necessarily happens before we know the tenant,
        // so this one lookup is explicitly elevated and scoped to nothing else.
        $context = app(TenantContext::class);

        $credential = $context->withoutScope(
            fn () => WebsiteCredential::with('website')
                ->where('key_id', $keyId)
                ->where('status', 'active')
                ->first()
        );

        if (! $credential) {
            // Same response as a bad signature: a caller probing for valid key
            // ids should not be able to tell "no such key" from "wrong secret".
            return $this->reject('invalid_signature', 'Signature verification failed.');
        }

        $canonical = implode("\n", [
            strtoupper($request->method()),
            $request->getPathInfo(),
            $timestamp,
            $nonce,
            hash('sha256', $request->getContent()),
        ]);

        $expected = hash_hmac('sha256', $canonical, $credential->secret());

        if (! hash_equals($expected, (string) $signature)) {
            return $this->reject('invalid_signature', 'Signature verification failed.');
        }

        // Burn the nonce. add() is atomic, so two concurrent replays cannot both
        // win the race. The TTL only needs to outlive the skew window: past that,
        // the timestamp check rejects the request anyway.
        $nonceKey = "plugin-nonce:{$keyId}:{$nonce}";

        if (! Cache::add($nonceKey, true, now()->addSeconds(self::MAX_SKEW_SECONDS * 2))) {
            return $this->reject('replayed_request', 'This request has already been processed.');
        }

        $website = $credential->website;

        if (! $website || $website->status === 'suspended') {
            return $this->reject('website_suspended', 'This website is not permitted to transact.', 403);
        }

        // From here on the request is bound to exactly one tenant, and RLS will
        // hold it there for the rest of its life.
        $context->setTenant($credential->tenant_id);

        $request->attributes->set('website', $website);
        $request->attributes->set('credential', $credential);

        $response = $next($request);

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $response;
    }

    private function reject(string $code, string $message, int $status = 401): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
