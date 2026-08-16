<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\Audit\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies a verified Stripe event to our own records.
 *
 * Everything here must be safe to run twice. The controller dedupes on
 * stripe_event_id, but a job can still be retried after a partial failure, so
 * each handler updates toward a known end state rather than incrementing or
 * appending blindly.
 *
 * Stripe is the authority on whether money moved. Where our record disagrees
 * with the event, the event wins.
 */
class ProcessStripeWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 600];

    public function __construct(public readonly string $webhookEventId)
    {
    }

    public function handle(TenantContext $context, AuditLogger $audit): void
    {
        $context->elevate();

        $record = DB::table('webhook_events')->where('id', $this->webhookEventId)->first();

        if (! $record || $record->status === 'processed') {
            return;
        }

        $payload = json_decode($record->payload, true);
        $object = $payload['data']['object'] ?? [];

        if ($record->tenant_id) {
            $context->setTenant($record->tenant_id);
        }

        try {
            $handled = match ($record->type) {
                'payment_intent.succeeded' => $this->paymentSucceeded($object, $audit),
                'payment_intent.payment_failed' => $this->paymentFailed($object),
                'charge.refunded' => $this->chargeRefunded($object),
                'charge.dispute.created' => $this->disputeCreated($object),
                'charge.dispute.closed' => $this->disputeClosed($object),
                // Unhandled types are still stored, so adding a handler later
                // does not mean the history is gone.
                default => false,
            };

            $context->elevate();

            DB::table('webhook_events')->where('id', $this->webhookEventId)->update([
                'status' => $handled ? 'processed' : 'ignored',
                'processed_at' => now(),
                'attempts' => $record->attempts + 1,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $context->elevate();

            DB::table('webhook_events')->where('id', $this->webhookEventId)->update([
                'status' => 'failed',
                'processing_error' => Str::limit($e->getMessage(), 1000, ''),
                'attempts' => $record->attempts + 1,
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * The event that actually confirms money moved. Everything the plugin shows
     * a customer should ultimately derive from this.
     */
    private function paymentSucceeded(array $intent, AuditLogger $audit): bool
    {
        $order = $this->findOrder($intent);

        if (! $order) {
            return false;
        }

        $charge = $intent['latest_charge'] ?? null;

        DB::transaction(function () use ($order, $intent, $charge, $audit) {
            $order->forceFill([
                'status' => 'paid',
                'checkout_token_hash' => null, // Consume the checkout link.
                'checkout_consumed_at' => now(),
            ])->save();

            Transaction::where('order_id', $order->id)
                ->where('type', 'charge')
                ->where('stripe_payment_intent_id', $intent['id'])
                ->update([
                    'status' => 'succeeded',
                    'stripe_charge_id' => is_string($charge) ? $charge : null,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            $audit->record('payment.succeeded', $order, [
                'amount_minor' => $order->amount_minor,
                'currency' => $order->currency,
            ], actorType: 'stripe');
        });

        return true;
    }

    private function paymentFailed(array $intent): bool
    {
        $order = $this->findOrder($intent);

        if (! $order) {
            return false;
        }

        $error = $intent['last_payment_error'] ?? [];

        DB::transaction(function () use ($order, $intent, $error) {
            $order->forceFill(['status' => 'failed'])->save();

            Transaction::where('order_id', $order->id)
                ->where('stripe_payment_intent_id', $intent['id'])
                ->update([
                    'status' => 'failed',
                    'failure_code' => $error['code'] ?? null,
                    'failure_message' => isset($error['message']) ? Str::limit($error['message'], 250, '') : null,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return true;
    }

    /**
     * Refunds can originate outside our dashboard — from the Stripe dashboard,
     * or from a dispute resolving — so this reconciles rather than assumes.
     */
    private function chargeRefunded(array $charge): bool
    {
        $transaction = Transaction::where('stripe_charge_id', $charge['id'] ?? '')->first()
            ?? Transaction::where('stripe_payment_intent_id', $charge['payment_intent'] ?? '')->first();

        if (! $transaction) {
            return false;
        }

        $order = $transaction->order;
        $refundedTotal = (int) ($charge['amount_refunded'] ?? 0);

        DB::transaction(function () use ($order, $transaction, $charge, $refundedTotal) {
            // Assign the total rather than adding to it: replaying this event
            // must not inflate the refunded figure.
            $order->forceFill([
                'amount_refunded_minor' => $refundedTotal,
                'status' => $refundedTotal >= $order->amount_minor ? 'refunded' : 'partially_refunded',
            ])->save();

            foreach ($charge['refunds']['data'] ?? [] as $stripeRefund) {
                Refund::updateOrCreate(
                    ['stripe_refund_id' => $stripeRefund['id']],
                    [
                        'tenant_id' => $order->tenant_id,
                        'order_id' => $order->id,
                        'transaction_id' => $transaction->id,
                        'amount_minor' => (int) $stripeRefund['amount'],
                        'reason' => $stripeRefund['reason'] ?? null,
                        'status' => $stripeRefund['status'] ?? 'succeeded',
                    ]
                );
            }
        });

        return true;
    }

    private function disputeCreated(array $dispute): bool
    {
        $transaction = Transaction::where('stripe_charge_id', $dispute['charge'] ?? '')->first();

        if (! $transaction) {
            return false;
        }

        DB::table('disputes')->updateOrInsert(
            ['stripe_dispute_id' => $dispute['id']],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $transaction->tenant_id,
                'order_id' => $transaction->order_id,
                'transaction_id' => $transaction->id,
                'amount_minor' => (int) $dispute['amount'],
                'reason' => $dispute['reason'] ?? null,
                'status' => $dispute['status'],
                'evidence_due_at' => isset($dispute['evidence_details']['due_by'])
                    ? now()->setTimestamp((int) $dispute['evidence_details']['due_by'])
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Order::whereKey($transaction->order_id)->update(['status' => 'disputed']);

        return true;
    }

    private function disputeClosed(array $dispute): bool
    {
        DB::table('disputes')
            ->where('stripe_dispute_id', $dispute['id'])
            ->update(['status' => $dispute['status'], 'updated_at' => now()]);

        return true;
    }

    /**
     * Find our order from a Stripe object, preferring the metadata we set at
     * creation and falling back to the PaymentIntent id.
     */
    private function findOrder(array $intent): ?Order
    {
        $orderId = $intent['metadata']['platform_order_id'] ?? null;

        if ($orderId && $order = Order::find($orderId)) {
            return $order;
        }

        return Transaction::where('stripe_payment_intent_id', $intent['id'] ?? '')
            ->first()?->order;
    }
}
