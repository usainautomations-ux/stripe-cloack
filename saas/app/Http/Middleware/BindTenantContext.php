<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the logged-in dashboard user's tenant to the database connection for
 * the lifetime of the request.
 *
 * This runs after 'auth', so a user is guaranteed to exist here. A platform
 * administrator (tenant_id === null, platform role) is elevated to a
 * cross-tenant view; everyone else is pinned to their own tenant, which is
 * what makes the RLS policies actually filter their queries.
 */
class BindTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        if ($user->isPlatformAdmin()) {
            $context->elevate();
        } else {
            $context->setTenant($user->tenant_id);
        }

        return $next($request);
    }
}
