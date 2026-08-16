<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessStripeWebhook;
use App\Models\StripeAccount;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Receives Stripe webhooks.
 *
 * This endpoint does as little as possible: verify, persist, enqueue, return
 * 200. Everything else happens in a job. Two reasons, both learned the hard way
 * by everyone who has ever built this:
 *
 *  - Stripe times out at 20 seconds and retries on failure. Doing real work
 *    inline turns a slow database into a storm of duplicate deliveries.
 *  - A crash midway through processing must not lose the event. Once the row
 *    is committed, the work can be retried from our side rather than Stripe's.
 *
 * Delivery is at-least-once, so duplicates are guaranteed, not hypothetical.
 * The unique index on stripe_event_id is what makes them harmless.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function __invoke(Request $request, string $stripeAccount): JsonResponse
    {
        // Stripe has no tenant identity; the account in the URL is what tells us
        // whose webhook this is, and which secret to verify against.
        $account = $this->context->withoutScope(
            fn () => StripeAccount::withTrashed()->find($stripeAccount)
        );

        if (! $account) {
            // 404 rather than an error Stripe would retry forever.
            return response()->json(['error' => 'Unknown account.'], 404);
        }

        $secret = $account->webhookSecret();

        if (! $secret) {
            return response()->json(['error' => 'Webhook signing secret not configured.'], 400);
        }

        // The RAW body, byte for byte. Any re-encoding here breaks verification.
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException) {
            // Unsigned or forged. Nothing is persisted, because an attacker must
            // not be able to fill this table by posting garbage.
            return response()->json(['error' => 'Signature verification failed.'], 400);
        } catch (\UnexpectedValueException) {
            return response()->json(['error' => 'Malformed payload.'], 400);
        }

        $eventId = null;

        try {
            $eventId = $this->context->withoutScope(function () use ($event, $account, $payload) {
                $id = (string) Str::uuid();

                DB::table('webhook_events')->insert([
                    'id' => $id,
                    'tenant_id' => $account->tenant_id,
                    'stripe_account_id' => $account->id,
                    'stripe_event_id' => $event->id,
                    'type' => $event->type,
                    'signature_verified' => true,
                    'payload' => $payload,
                    'status' => 'pending',
                    'received_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $id;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Already have it. Acknowledge so Stripe stops retrying, and do not
            // process it a second time.
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        ProcessStripeWebhook::dispatch($eventId);

        return response()->json(['received' => true]);
    }
}
