<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Hosted checkout
|--------------------------------------------------------------------------
|
| Served from the platform domain so that the shopper's browser — and
| therefore Stripe — never sees the originating client site.
|
| These routes are reached by shoppers with no account, authenticated solely
| by a single-use token in the URL.
|
*/

Route::get('/checkout/{order}', [CheckoutController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('checkout.show');

Route::get('/checkout/{order}/complete', [CheckoutController::class, 'complete'])
    ->middleware('throttle:60,1')
    ->name('checkout.complete');

/*
|--------------------------------------------------------------------------
| Stripe webhooks
|--------------------------------------------------------------------------
|
| Outside the web middleware group's CSRF protection: Stripe cannot present a
| CSRF token, and authenticity is established by the webhook signature instead.
| The route carries the account id so we know which signing secret to check
| against before trusting a single byte of the payload.
|
*/

Route::post('/stripe/webhook/{stripeAccount}', StripeWebhookController::class)
    ->name('stripe.webhook');
