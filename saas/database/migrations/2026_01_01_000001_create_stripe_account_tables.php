<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe accounts and the assignment matrix that controls who may use them.
 *
 * Secret material is stored as ciphertext produced by the envelope encrypter
 * (see App\Services\Crypto\SecretCipher). The plaintext exists only in memory,
 * only for the duration of an outbound Stripe call. No column here is ever
 * serialized into an API response.
 *
 * secret_key_last4 exists so the dashboard can show "sk_live_…4f2a" for
 * operator recognition without decrypting anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('label');
            $table->string('mode', 8); // test | live
            $table->string('status', 20)->default('active'); // active | suspended

            $table->string('publishable_key');
            $table->text('secret_key_ciphertext');
            $table->string('secret_key_last4', 8);
            $table->text('webhook_secret_ciphertext')->nullable();

            // Sent to Stripe on every charge. Deliberately platform-branded so a
            // cardholder statement never reveals the originating client domain.
            $table->string('statement_descriptor', 22)->nullable();

            $table->timestamp('key_rotated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'label']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stripe_account_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('stripe_account_id')->constrained('stripe_accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One grant per (account, user). Re-granting is idempotent.
            $table->unique(['stripe_account_id', 'user_id']);
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_account_assignments');
        Schema::dropIfExists('stripe_accounts');
    }
};
