<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\Audit\AuditLogger;
use App\Services\Website\WebsiteRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WebsiteRegistrationController extends Controller
{
    public function __construct(
        private readonly WebsiteRegistrar $registrar,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * POST /v1/website/register
     *
     * The only unsigned endpoint. Returns the credential secret exactly once —
     * it is never retrievable again, by anyone, including through the dashboard.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setup_token' => ['required', 'string', 'max:128'],
            'site_url' => ['required', 'string', 'max:255'],
            'challenge_url' => ['required', 'string', 'max:512', 'url'],
            'plugin_version' => ['nullable', 'string', 'max:20'],
            'wp_version' => ['nullable', 'string', 'max:20'],
            'php_version' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $result = $this->registrar->register(
                $validated['setup_token'],
                $validated['site_url'],
                $validated['challenge_url'],
                $validated
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => ['code' => 'registration_failed', 'message' => $e->getMessage()],
            ], 422);
        }

        $website = $result['website'];

        $this->audit->record('website.registered', $website, [
            'domain' => $website->domain,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'site_id' => $website->id,
            'site_key' => $result['key_id'],
            'site_secret' => $result['secret'],
            'status' => $website->status,
        ], 201);
    }

    /**
     * POST /v1/website/{website}/verify
     *
     * Signed with the credential just issued, which is itself partial evidence;
     * the callback inside verify() is what actually settles domain ownership.
     */
    public function verify(Request $request, string $website): JsonResponse
    {
        $model = $this->resolveWebsite($request, $website);

        if (! $model) {
            return $this->wrongSite();
        }

        $verified = $this->registrar->verify($model);

        $this->audit->record(
            $verified ? 'website.verified' : 'website.verification_failed',
            $model,
            ['domain' => $model->domain]
        );

        if (! $verified) {
            return response()->json([
                'error' => [
                    'code' => 'verification_failed',
                    'message' => 'We could not reach this site to confirm the plugin is installed. Check that the site is publicly reachable over HTTPS and try again.',
                ],
            ], 422);
        }

        return response()->json([
            'status' => 'verified',
            'ready_for_payments' => $model->fresh()->canAcceptPayments(),
        ]);
    }

    /**
     * GET /v1/website/{website}/handshake
     *
     * Liveness ping. Also the channel through which the dashboard learns that a
     * site is running an outdated plugin.
     */
    public function handshake(Request $request, string $website): JsonResponse
    {
        $model = $this->resolveWebsite($request, $website);

        if (! $model) {
            return $this->wrongSite();
        }

        $model->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'status' => $model->status,
            'ready_for_payments' => $model->canAcceptPayments(),
            'stripe_account_assigned' => $model->stripe_account_id !== null,
        ]);
    }

    /**
     * POST /v1/website/{website}/disconnect
     */
    public function disconnect(Request $request, string $website): JsonResponse
    {
        $model = $this->resolveWebsite($request, $website);

        if (! $model) {
            return $this->wrongSite();
        }

        $model->credentials()->where('status', 'active')->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $model->forceFill(['status' => 'pending'])->save();

        $this->audit->record('website.disconnected', $model, ['domain' => $model->domain]);

        return response()->json(['status' => 'disconnected']);
    }

    /**
     * The route parameter is untrusted; the authenticated credential is not.
     * Requiring them to agree stops a site with valid credentials from acting
     * on a different site's record by changing the id in the URL.
     */
    private function resolveWebsite(Request $request, string $routeId): ?Website
    {
        $authenticated = $request->attributes->get('website');

        return $authenticated instanceof Website && $authenticated->id === $routeId
            ? $authenticated
            : null;
    }

    private function wrongSite(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'website_mismatch',
                'message' => 'These credentials do not belong to the requested website.',
            ],
        ], 403);
    }
}
