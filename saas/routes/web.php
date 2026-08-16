<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\StripeAccountController;
use App\Http\Controllers\Dashboard\WebsiteController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Dashboard authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
|
| 'tenant' must run after 'auth' — it reads $request->user() to decide which
| tenant's data this connection may see, and it is what makes the RLS
| policies actually filter every query a controller below makes.
|
*/

Route::middleware(['auth', 'tenant'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::get('/websites/create', [WebsiteController::class, 'create'])->name('websites.create');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');
    Route::put('/websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');

    Route::get('/stripe-accounts', [StripeAccountController::class, 'index'])->name('stripe-accounts.index');
    Route::get('/stripe-accounts/create', [StripeAccountController::class, 'create'])->name('stripe-accounts.create');
    Route::post('/stripe-accounts', [StripeAccountController::class, 'store'])->name('stripe-accounts.store');
    Route::get('/stripe-accounts/{stripeAccount}', [StripeAccountController::class, 'show'])->name('stripe-accounts.show');
    Route::post('/stripe-accounts/{stripeAccount}/assignments', [StripeAccountController::class, 'toggleAssignment'])->name('stripe-accounts.assignments');
    Route::post('/stripe-accounts/{stripeAccount}/rotate', [StripeAccountController::class, 'rotate'])->name('stripe-accounts.rotate');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
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
