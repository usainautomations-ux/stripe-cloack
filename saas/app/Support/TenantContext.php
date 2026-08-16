<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Carries the acting tenant for the lifetime of a request, and pushes it down
 * into Postgres so the RLS policies have something to compare against.
 *
 * Two things make this safe to rely on:
 *
 *  1. set_config(..., false) sets the value for the SESSION, not the
 *     transaction. Database connections are reused between requests, so the
 *     value MUST be written on every request — never assumed to be carried
 *     over, and never left set for the next request to inherit.
 *
 *  2. Clearing sets the empty string rather than NULL. current_setting() with
 *     missing_ok returns NULL for an unset variable and '' for one explicitly
 *     cleared; either way the policy comparison fails closed, but writing ''
 *     means "we deliberately cleared this" rather than "nobody ever set it".
 */
class TenantContext
{
    private ?string $tenantId = null;

    private bool $bypass = false;

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function hasBypass(): bool
    {
        return $this->bypass;
    }

    /**
     * Bind the connection to a single tenant. Every subsequent query on this
     * connection sees only that tenant's rows.
     */
    public function setTenant(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->bypass = false;

        DB::statement("select set_config('app.current_tenant', ?, false)", [$tenantId]);
        DB::statement("select set_config('app.bypass_tenant_scope', 'off', false)");
    }

    /**
     * Elevate to a cross-tenant view. Reserved for platform administrators and
     * for system paths that legitimately have no tenant yet — authentication
     * lookups, webhook ingestion, and plugin key resolution.
     */
    public function elevate(): void
    {
        $this->bypass = true;
        $this->tenantId = null;

        DB::statement("select set_config('app.bypass_tenant_scope', 'on', false)");
        DB::statement("select set_config('app.current_tenant', '', false)");
    }

    /**
     * Drop all context. The connection can now read nothing until a tenant is
     * set again — which is the correct resting state.
     */
    public function clear(): void
    {
        $this->tenantId = null;
        $this->bypass = false;

        DB::statement("select set_config('app.current_tenant', '', false)");
        DB::statement("select set_config('app.bypass_tenant_scope', 'off', false)");
    }

    /**
     * Run a callback with cross-tenant access, then restore whatever context was
     * in force before. Used by the narrow set of operations that must look up a
     * row before they know which tenant it belongs to.
     *
     * @template T
     * @param  Closure():T  $callback
     * @return T
     */
    public function withoutScope(Closure $callback)
    {
        $previousTenant = $this->tenantId;
        $previousBypass = $this->bypass;

        $this->elevate();

        try {
            return $callback();
        } finally {
            if ($previousTenant !== null) {
                $this->setTenant($previousTenant);
            } elseif ($previousBypass) {
                $this->elevate();
            } else {
                $this->clear();
            }
        }
    }
}
