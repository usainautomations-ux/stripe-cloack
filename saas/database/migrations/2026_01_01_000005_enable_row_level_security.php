<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Postgres Row-Level Security: the second, database-enforced isolation layer.
 *
 * The application already scopes every query by tenant. This exists for the day
 * somebody forgets. The policies read two session variables:
 *
 *   app.current_tenant        the acting tenant's uuid
 *   app.bypass_tenant_scope   'on' for platform-admin and system contexts
 *
 * When NEITHER is set, current_setting(..., true) returns NULL, the comparison
 * evaluates to NULL, and the row is not visible. The default is therefore deny,
 * not allow — a query that runs without tenant context returns nothing rather
 * than everything.
 *
 * FORCE ROW LEVEL SECURITY matters here: usman_app owns these tables, and a
 * table owner is otherwise exempt from its own policies. Without FORCE, every
 * policy below would be silently inert.
 */
return new class extends Migration
{
    /**
     * Tables keyed by the column that carries the tenant.
     */
    private const SCOPED_TABLES = [
        'users' => 'tenant_id',
        'stripe_accounts' => 'tenant_id',
        'stripe_account_assignments' => 'tenant_id',
        'websites' => 'tenant_id',
        'website_credentials' => 'tenant_id',
        'setup_tokens' => 'tenant_id',
        'customers' => 'tenant_id',
        'orders' => 'tenant_id',
        'transactions' => 'tenant_id',
        'refunds' => 'tenant_id',
        'disputes' => 'tenant_id',
        'webhook_events' => 'tenant_id',
        'audit_logs' => 'tenant_id',
        'api_request_logs' => 'tenant_id',
        // The tenants table scopes on its own primary key.
        'tenants' => 'id',
    ];

    public function up(): void
    {
        foreach (self::SCOPED_TABLES as $table => $column) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            DB::statement(<<<SQL
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                USING (
                    current_setting('app.bypass_tenant_scope', true) = 'on'
                    OR {$column}::text = current_setting('app.current_tenant', true)
                )
                WITH CHECK (
                    current_setting('app.bypass_tenant_scope', true) = 'on'
                    OR {$column}::text = current_setting('app.current_tenant', true)
                )
            SQL);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::SCOPED_TABLES) as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
