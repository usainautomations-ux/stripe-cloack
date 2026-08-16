<?php

namespace App\Auth;

use App\Support\TenantContext;
use Illuminate\Auth\EloquentUserProvider;

/**
 * The auth guard's user lookups run before any tenant is knowable — that is
 * the definition of authentication — so they need the same cross-tenant read
 * access the login form itself already uses via TenantContext::withoutScope().
 *
 * Without this, every request after the first would silently fail to
 * re-authenticate: the session cookie is valid, Illuminate\Auth\SessionGuard
 * calls retrieveById() to reload the User row, RLS on the users table rejects
 * the SELECT because tenant context hasn't been bound yet (binding it is what
 * the 'tenant' middleware does, and it can only run once the user — and
 * therefore their tenant — is known), and the query returns nothing. The
 * guard falls back to treating the request as a guest even though the session
 * was genuinine, and every authenticated route bounces back to login.
 *
 * Only the three lookup methods below cross tenants. Every query the rest of
 * the request makes still goes through 'tenant' middleware and RLS exactly as
 * before — this does not widen access anywhere else.
 */
class TenantAwareUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        return app(TenantContext::class)->withoutScope(
            fn () => parent::retrieveById($identifier)
        );
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return app(TenantContext::class)->withoutScope(
            fn () => parent::retrieveByToken($identifier, $token)
        );
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        return app(TenantContext::class)->withoutScope(
            fn () => parent::retrieveByCredentials($credentials)
        );
    }
}
