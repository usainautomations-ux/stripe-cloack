<?php

namespace Tests\Feature;

use App\Models\StripeAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Proves tenant isolation is enforced by PostgreSQL, not merely by Eloquent.
 *
 * Every assertion here deliberately uses raw DB::select rather than a model
 * query. A test written against Eloquent would pass even if RLS were disabled
 * entirely, because the global scope would be doing all the work — which is
 * exactly the failure mode this suite exists to rule out.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $acme;

    private Tenant $globex;

    private Website $acmeSite;

    private Website $globexSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $this->acmeSite = $this->makeSiteFor($this->acme, 'acme-store.com', 'ops@acme.test');
        $this->globexSite = $this->makeSiteFor($this->globex, 'globex-store.com', 'ops@globex.test');
    }

    private function makeSiteFor(Tenant $tenant, string $domain, string $email): Website
    {
        $owner = User::create([
            'tenant_id' => $tenant->id,
            'role_key' => 'tenant_owner',
            'name' => 'Owner of ' . $tenant->name,
            'email' => $email,
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return Website::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'domain' => $domain,
            'status' => 'verified',
        ]);
    }

    public function test_raw_sql_inside_a_tenant_sees_only_that_tenants_websites(): void
    {
        $this->asTenant($this->acme->id);

        $rows = DB::select('select domain from websites');

        $this->assertCount(1, $rows, 'A tenant-scoped connection returned rows from more than one tenant.');
        $this->assertSame('acme-store.com', $rows[0]->domain);
    }

    public function test_a_tenant_cannot_read_another_tenants_row_even_when_naming_its_id(): void
    {
        $this->asTenant($this->acme->id);

        // The most direct possible attempt: ask for the other tenant's row by
        // primary key, with no WHERE tenant_id filter to "forget".
        $rows = DB::select('select domain from websites where id = ?', [$this->globexSite->id]);

        $this->assertEmpty($rows, 'RLS allowed a cross-tenant read by primary key.');
    }

    public function test_a_connection_with_no_tenant_context_sees_nothing(): void
    {
        $this->asNobody();

        $this->assertEmpty(
            DB::select('select id from websites'),
            'A connection with no tenant context could read tenant data — the policy is failing open.'
        );
        $this->assertEmpty(
            DB::select('select id from stripe_accounts'),
            'Stripe accounts were readable without tenant context.'
        );
    }

    public function test_a_tenant_cannot_insert_a_row_belonging_to_another_tenant(): void
    {
        $this->asTenant($this->acme->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Acme's connection tries to plant a website inside Globex. The policy's
        // WITH CHECK clause is what must stop this.
        DB::insert(
            'insert into websites (id, tenant_id, owner_user_id, domain, status, created_at, updated_at)
             values (?, ?, ?, ?, ?, now(), now())',
            [
                (string) \Illuminate\Support\Str::uuid(),
                $this->globex->id,
                $this->globexSite->owner_user_id,
                'planted-by-acme.com',
                'verified',
            ]
        );
    }

    public function test_stripe_accounts_are_isolated_between_tenants(): void
    {
        $acmeAccount = $this->makeStripeAccount($this->acme, 'Acme Live');
        $this->makeStripeAccount($this->globex, 'Globex Live');

        $this->asTenant($this->acme->id);

        $rows = DB::select('select id, label from stripe_accounts');

        $this->assertCount(1, $rows);
        $this->assertSame($acmeAccount->id, $rows[0]->id);
        $this->assertSame('Acme Live', $rows[0]->label);
    }

    public function test_elevated_context_sees_across_tenants(): void
    {
        // Platform administration and webhook ingestion depend on this working —
        // isolation that cannot be deliberately lifted would break both.
        $this->tenantContext()->elevate();

        $this->assertCount(2, DB::select('select id from websites'));
    }

    public function test_switching_tenants_switches_the_visible_rows(): void
    {
        // Connections are reused between requests, so a stale session variable
        // would leak the previous tenant's data into the next request.
        $this->asTenant($this->acme->id);
        $this->assertSame('acme-store.com', DB::select('select domain from websites')[0]->domain);

        $this->asTenant($this->globex->id);
        $this->assertSame('globex-store.com', DB::select('select domain from websites')[0]->domain);
    }

    private function makeStripeAccount(Tenant $tenant, string $label): StripeAccount
    {
        $account = new StripeAccount([
            'tenant_id' => $tenant->id,
            'label' => $label,
            'mode' => 'test',
            'publishable_key' => 'pk_test_' . bin2hex(random_bytes(8)),
        ]);

        $account->setSecretKey('sk_test_' . bin2hex(random_bytes(12)));
        $account->save();

        return $account;
    }
}
