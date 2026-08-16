<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Services\Crypto\KeyProvider;
use App\Services\Crypto\LocalKeyProvider;
use App\Services\Crypto\SecretCipher;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request. Binding it as a singleton is what
        // makes "the acting tenant" a single source of truth rather than
        // something each query re-derives and can get wrong.
        $this->app->singleton(TenantContext::class);

        $this->app->singleton(KeyProvider::class, function () {
            return match (config('secrets.provider')) {
                'local' => new LocalKeyProvider(config('secrets.local.master_key')),
                'kms' => throw new RuntimeException(
                    'The KMS key provider is not implemented yet. Set SECRETS_PROVIDER=local for now.'
                ),
                default => throw new RuntimeException(
                    'Unknown secrets provider: ' . config('secrets.provider')
                ),
            };
        });

        $this->app->singleton(SecretCipher::class, function ($app) {
            return new SecretCipher($app->make(KeyProvider::class));
        });
    }

    public function boot(): void
    {
        // The auth guard's own user lookups (retrieveById on every request,
        // retrieveByCredentials at login) run before any tenant is known —
        // that is what authentication IS — so they need to read across
        // tenants the same way the login form's manual lookup already does.
        // Without this provider, RLS on the users table silently rejects
        // every session's re-authentication after the first request. See
        // TenantAwareUserProvider for the full explanation.
        Auth::provider('tenant_aware_eloquent', function ($app, array $config) {
            return new TenantAwareUserProvider($app['hash'], $config['model']);
        });

        // Reject lazy loading outside production. An N+1 in a transaction report
        // is a performance bug; an unexpected lazy load inside a tenant-scoped
        // loop is a correctness question, and both are easier to catch here.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Assigning an attribute that has no column is almost always a typo, and
        // silently discarding it can mean a security flag never gets persisted.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
