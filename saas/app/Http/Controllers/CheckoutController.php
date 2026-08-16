<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Stripe\StripeClientFactory;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The hosted payment page, served from the platform domain.
 *
 * This page is the whole reason Stripe never sees the client domain. The card
 * form is served by us, so the browser's origin, referrer, and every request
 * Stripe.js makes belong to the platform — not to the shop the customer
 * started from.
 *
 * The shopper arrives here unauthenticated, carrying only a single-use token,
 * so the access rules are unusual: no session, no login, and the token must be
 * treated as the entire credential.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly TenantContext $context,
    ) {
    }

    public function show(Request $request, string $order): View
    {
        $token = (string) $request->query('token');

        if ($token === '') {
            throw new NotFoundHttpException();
        }

        // The shopper has no tenant identity, so this lookup is elevated and
        // then immediately pinned to the order's own tenant.
        $model = $this->context->withoutScope(
            fn () => Order::with(['stripeAccount', 'website'])->find($order)
        );

        if (! $model || ! hash_equals((string) $model->checkout_token_hash, hash('sha256', $token))) {
            throw new NotFoundHttpException();
        }

        if ($model->checkout_expires_at?->isPast()) {
            return view('checkout.expired', ['order' => $model]);
        }

        if ($model->isPaid()) {
            return view('checkout.already-paid', ['order' => $model]);
        }

        $this->context->setTenant($model->tenant_id);

        $transaction = $model->transactions()->where('type', 'charge')->latest()->first();

        $intent = $this->stripe->for($model->stripeAccount)
            ->paymentIntents
            ->retrieve($transaction->stripe_payment_intent_id);

        return view('checkout.show', [
            'order' => $model,
            'clientSecret' => $intent->client_secret,
            'publishableKey' => $model->stripeAccount->publishable_key,
            // Deliberately the platform's name, never the website's.
            'merchantName' => config('app.platform_name', 'usman.com'),
        ]);
    }

    /**
     * Where Stripe returns the shopper after payment. The platform, not the
     * client site, is the return target — then we forward onward.
     */
    public function complete(Request $request, string $order): View
    {
        $model = $this->context->withoutScope(fn () => Order::find($order));

        if (! $model) {
            throw new NotFoundHttpException();
        }

        // The status shown here is provisional. The webhook is authoritative,
        // and the plugin is told never to trust this page.
        return view('checkout.complete', [
            'order' => $model,
            'returnUrl' => $model->return_url,
        ]);
    }
}
