<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only observability tables.
 *
 * webhook_events is not just a log — the unique constraint on stripe_event_id is
 * the actual deduplication mechanism. Stripe retries deliveries, and at-least-once
 * delivery means the same event WILL arrive twice; the insert failing is how we
 * find out cheaply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('stripe_account_id')->nullable()->constrained('stripe_accounts')->nullOnDelete();

            // Dedupe key. A duplicate insert is the signal to skip processing.
            $table->string('stripe_event_id')->unique();
            $table->string('type', 80);
            $table->boolean('signature_verified')->default(false);

            $table->jsonb('payload');
            $table->string('status', 20)->default('pending'); // pending | processed | failed | ignored
            $table->text('processing_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['stripe_account_id', 'type']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('actor_type', 20)->default('user'); // user | plugin | system | stripe
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->uuid('entity_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('website_id')->nullable()->constrained('websites')->cascadeOnDelete();

            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->boolean('signature_valid')->nullable();
            $table->string('rejection_reason', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['website_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_events');
    }
};
