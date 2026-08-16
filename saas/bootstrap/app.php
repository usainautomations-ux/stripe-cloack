<?php

use App\Http\Middleware\AuthenticatePluginRequest;
use App\Http\Middleware\BindTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'plugin.auth' => AuthenticatePluginRequest::class,
            // Deliberately declared after 'auth' wherever it is used — it reads
            // $request->user(), so it must run second. Custom aliases outside
            // Laravel's built-in priority list keep the relative order they are
            // given in a route's middleware array, which is what makes this safe.
            'tenant' => BindTenantContext::class,
        ]);

        // Stripe signs the exact bytes it sent. Any transformation of the body
        // before signature verification — trimming, re-encoding, unicode
        // normalisation — invalidates it, so this path stays untouched.
        $middleware->trimStrings(except: ['stripe/*']);

        // Stripe cannot present a CSRF token. Authenticity for these requests
        // comes from the webhook signature, which is strictly stronger.
        $middleware->validateCsrfTokens(except: ['stripe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->is('stripe/*') || $request->expectsJson(),
        );
    })->create();
