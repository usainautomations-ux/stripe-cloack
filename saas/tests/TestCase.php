<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests start elevated so that arrange-phase factory inserts work at all —
     * under FORCE ROW LEVEL SECURITY, an insert with no tenant context fails the
     * policy's WITH CHECK clause.
     *
     * Tests that are actually about isolation drop back down with asTenant()
     * and assert from inside a single tenant's view.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantContext()->elevate();
    }

    protected function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    /**
     * Bind the connection to one tenant, the way a real authenticated request
     * would. Everything read after this is subject to the RLS policies.
     */
    protected function asTenant(string $tenantId): static
    {
        $this->tenantContext()->setTenant($tenantId);

        return $this;
    }

    /**
     * Drop all context. Nothing tenant-scoped should be readable afterwards.
     */
    protected function asNobody(): static
    {
        $this->tenantContext()->clear();

        return $this;
    }
}
