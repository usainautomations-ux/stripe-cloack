<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The money tables.
 *
 * Amounts are bigint minor units (4999 = $49.99) — never floats. Currency is a
 * 3-char ISO 4217 code stored lowercase to match Stripe's own convention.
 *
 * The chain tenant → website → stripe_account → order → transaction is carried
 * as real foreign keys on every row rather than reconstructed by join at report
 * time, so a transaction can never be orphaned from the account that processed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('website_id')->constrained('websites')->cascadeOnDelete();

            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'email']);
            $table->index(['tenant_id', 'email']);
        });

        /**
         * One shopper can transact against several Stripe accounts, and Stripe
         * customer ids are scoped per account — so the mapping is many-to-many
         * rather than a column on customers.
         */
        Schema::create('stripe_customer_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('stripe_account_id')->constrained('stripe_accounts')->cascadeOnDelete();
            $table->string('stripe_customer_id');
            $table->timestamps();

            $table->unique(['customer_id', 'stripe_account_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignUuid('stripe_account_id')->constrained('stripe_accounts')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // The originating site's own identifier (e.g. a WooCommerce order id).
            $table->string('order_ref');

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            // pending | processing | paid | failed | refunded | partially_refunded | disputed | cancelled

            // Product-agnostic. Never contains the originating domain.
            $table->string('description')->nullable();
            $table->string('return_url')->nullable();

            // Hosted checkout handoff: single-use, short-lived.
            $table->string('checkout_token_hash', 64)->nullable()->unique();
            $table->timestamp('checkout_expires_at')->nullable();
            $table->timestamp('checkout_consumed_at')->nullable();

            $table->bigInteger('amount_refunded_minor')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Same order_ref replayed by the same site must not create a second order.
            $table->unique(['website_id', 'order_ref']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['stripe_account_id', 'created_at']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('stripe_account_id')->constrained('stripe_accounts')->restrictOnDelete();

            $table->string('type', 20); // charge | refund | dispute
            $table->string('status', 30);

            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('stripe_payment_intent_id');
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();

            $table->string('stripe_refund_id')->nullable()->unique();
            $table->bigInteger('amount_minor');
            $table->string('reason', 40)->nullable();
            $table->string('status', 20)->default('pending'); // pending | succeeded | failed | cancelled

            // Every refund carries the human who authorized it.
            $table->foreignUuid('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();

            $table->string('stripe_dispute_id')->unique();
            $table->bigInteger('amount_minor');
            $table->string('reason', 60)->nullable();
            $table->string('status', 40);

            $table->timestamp('evidence_due_at')->nullable();
            $table->timestamp('evidence_submitted_at')->nullable();
            $table->foreignUuid('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'evidence_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stripe_customer_links');
        Schema::dropIfExists('customers');
    }
};
