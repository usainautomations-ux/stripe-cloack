<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Website;
use App\Services\Payment\PaymentCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentCreator $payments)
    {
    }

    /**
     * POST /v1/payment/create
     *
     * Note what the request is NOT allowed to specify: the Stripe account. That
     * comes from the website's configuration, so a compromised site key cannot
     * be used to route money into an account the attacker controls.
     */
    public function create(Request $request): JsonResponse
    {
        $website = $request->attributes->get('website');

        $validated = $request->validate([
            'order_ref' => ['required', 'string', 'max:100'],
            // Minor units. The 50 floor is Stripe's own minimum charge; below it
            // every request would fail at the API with a confusing error.
            'amount' => ['required', 'integer', 'min:50', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:512'],
        ]);

        try {
            $result = $this->payments->create($website, $validated);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => ['code' => 'payment_creation_failed', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'order_id' => $result['order']->id,
            'checkout_url' => $result['checkout_url'],
            'status' => $result['order']->status,
            'amount' => $result['order']->amount_minor,
            'currency' => $result['order']->currency,
        ], 201);
    }

    /**
     * GET /v1/order/{orderRef}/status
     *
     * The plugin's authoritative source of truth. A WordPress site should never
     * mark an order complete based on the shopper returning to a success page —
     * only on what this endpoint says.
     */
    public function status(Request $request, string $orderRef): JsonResponse
    {
        /** @var Website $website */
        $website = $request->attributes->get('website');

        $order = Order::where('website_id', $website->id)
            ->where('order_ref', $orderRef)
            ->first();

        if (! $order) {
            return response()->json([
                'error' => ['code' => 'order_not_found', 'message' => 'No such order for this website.'],
            ], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_ref' => $order->order_ref,
            'status' => $order->status,
            'amount' => $order->amount_minor,
            'amount_refunded' => $order->amount_refunded_minor,
            'currency' => $order->currency,
            'paid' => $order->isPaid(),
            'updated_at' => $order->updated_at->toIso8601String(),
        ]);
    }
}
