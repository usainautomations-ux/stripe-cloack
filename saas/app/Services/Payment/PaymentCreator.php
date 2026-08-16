<?php

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Website;
use App\Services\Audit\AuditLogger;
use App\Services\Stripe\StripeClientFactory;
use App\Services\Stripe\StripePayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

/**
 * Turns a plugin's "create payment" call into a Stripe PaymentIntent.
 *
 * The routing decision — which Stripe account processes this — is NOT taken
 * from the request. It is read from the website record, which an administrator
 * configured. A compromised plugin can therefore choose amounts and currencies,
 * but cannot redirect funds to a different Stripe account.
 */
class PaymentCreator
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly StripePayloadBuilder $payload,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array{order_ref:string,amount:int,currency:string,customer_email:?string,customer_name:?string,description:?string,return_url:?string}  $input
     * @return array{order: Order, client_secret: string, checkout_url: string}
     */
    public function create(Website $website, array $input): array
    {
        if (! $website->canAcceptPayments()) {
            throw new RuntimeException(
                'This website is not ready to accept payments. It must be verified and assigned a Stripe account.'
            );
        }

        $account = $website->stripeAccount;

        // Idempotency at our own boundary. A plugin that retries after a timeout
        // must not open a second order — and must not create a second charge.
        $existing = Order::where('website_id', $website->id)
            ->where('order_ref', $input['order_ref'])
            ->first();

        if ($existing) {
            return $this->resumeExisting($existing);
        }

        $order = DB::transaction(function () use ($website, $account, $input) {
            $customer = $this->resolveCustomer($website, $input);

            return Order::create([
                'tenant_id' => $website->tenant_id,
                'website_id' => $website->id,
                'stripe_account_id' => $account->id,
                'customer_id' => $customer?->id,
                'order_ref' => $input['order_ref'],
                'amount_minor' => $input['amount'],
                'currency' => strtolower($input['currency']),
                'status' => 'pending',
                // Never the site's own description verbatim — it routinely
                // contains the shop name, which is the domain by another name.
                'description' => $this->payload->descriptionFor(
                    new Order(['id' => (string) Str::uuid()]),
                    $account
                ),
                'return_url' => $input['return_url'] ?? null,
            ]);
        });

        $params = $this->payload->paymentIntentParams($order, $account);

        // Last line of defence before anything leaves our network.
        $this->payload->assertNoDomainLeak($params, $website->domain);

        try {
            $intent = $this->stripe->for($account)->paymentIntents->create(
                $params,
                // Stripe-side idempotency, keyed on our order id, so a retry of
                // this same call cannot produce two PaymentIntents.
                ['idempotency_key' => 'order_' . $order->id]
            );
        } catch (ApiErrorException $e) {
            $order->forceFill([
                'status' => 'failed',
            ])->save();

            $this->recordTransaction($order, 'charge', 'failed', null, $e->getStripeCode(), $e->getMessage());

            throw new RuntimeException('Stripe declined to create this payment: ' . $e->getMessage(), previous: $e);
        }

        $checkoutToken = Str::random(48);

        $order->forceFill([
            'status' => 'processing',
            'checkout_token_hash' => hash('sha256', $checkoutToken),
            'checkout_expires_at' => now()->addMinutes(30),
        ])->save();

        $this->recordTransaction($order, 'charge', 'pending', $intent->id);

        $this->audit->record('payment.created', $order, [
            'amount_minor' => $order->amount_minor,
            'currency' => $order->currency,
            'stripe_account' => $account->label,
        ], actorType: 'plugin');

        return [
            'order' => $order,
            'client_secret' => $intent->client_secret,
            // Hosted on the platform domain. The shopper's browser never sends
            // the client domain to Stripe, because the payment form is not
            // served from the client domain.
            'checkout_url' => route('checkout.show', ['order' => $order->id, 'token' => $checkoutToken]),
        ];
    }

    /**
     * A retry of an order we already have. Returning the existing state is what
     * makes the endpoint safe to call twice.
     */
    private function resumeExisting(Order $order): array
    {
        if (in_array($order->status, ['paid', 'refunded', 'partially_refunded'], true)) {
            throw new RuntimeException('This order has already been paid.');
        }

        $transaction = $order->transactions()
            ->where('type', 'charge')
            ->latest()
            ->first();

        if (! $transaction?->stripe_payment_intent_id) {
            throw new RuntimeException('This order exists but has no payment attached. Contact support.');
        }

        $intent = $this->stripe->for($order->stripeAccount)
            ->paymentIntents
            ->retrieve($transaction->stripe_payment_intent_id);

        $checkoutToken = Str::random(48);

        $order->forceFill([
            'checkout_token_hash' => hash('sha256', $checkoutToken),
            'checkout_expires_at' => now()->addMinutes(30),
        ])->save();

        return [
            'order' => $order,
            'client_secret' => $intent->client_secret,
            'checkout_url' => route('checkout.show', ['order' => $order->id, 'token' => $checkoutToken]),
        ];
    }

    private function resolveCustomer(Website $website, array $input): ?Customer
    {
        if (empty($input['customer_email'])) {
            return null;
        }

        return Customer::firstOrCreate(
            [
                'website_id' => $website->id,
                'email' => strtolower($input['customer_email']),
            ],
            [
                'tenant_id' => $website->tenant_id,
                'name' => $input['customer_name'] ?? null,
            ]
        );
    }

    private function recordTransaction(
        Order $order,
        string $type,
        string $status,
        ?string $intentId = null,
        ?string $failureCode = null,
        ?string $failureMessage = null,
    ): Transaction {
        return Transaction::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'stripe_account_id' => $order->stripe_account_id,
            'type' => $type,
            'status' => $status,
            'stripe_payment_intent_id' => $intentId,
            'amount_minor' => $order->amount_minor,
            'currency' => $order->currency,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage ? Str::limit($failureMessage, 250, '') : null,
        ]);
    }
}
