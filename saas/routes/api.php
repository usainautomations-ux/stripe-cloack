<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebsiteRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plugin-facing API
|--------------------------------------------------------------------------
|
| Every route here is called by an installed WordPress plugin, never by a
| browser. They are stateless: no session, no cookies, no CSRF token — the
| HMAC signature is the whole of the authentication story.
|
| /v1/website/register is the sole unsigned endpoint, because a plugin has no
| secret until it completes that call. It is protected instead by a single-use
| setup token and a tight rate limit.
|
*/

Route::prefix('v1')->group(function () {

    Route::post('/website/register', [WebsiteRegistrationController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('website.register');

    Route::middleware(['plugin.auth', 'throttle:120,1'])->group(function () {

        Route::post('/website/{website}/verify', [WebsiteRegistrationController::class, 'verify'])
            ->name('website.verify');

        Route::get('/website/{website}/handshake', [WebsiteRegistrationController::class, 'handshake'])
            ->name('website.handshake');

        Route::post('/website/{website}/disconnect', [WebsiteRegistrationController::class, 'disconnect'])
            ->name('website.disconnect');

        Route::post('/payment/create', [PaymentController::class, 'create'])
            ->name('payment.create');

        Route::get('/order/{orderRef}/status', [PaymentController::class, 'status'])
            ->name('order.status');
    });
});
