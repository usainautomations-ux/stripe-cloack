<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Websites, their plugin credentials, and the short-lived tokens that bootstrap
 * the connection.
 *
 * Note the asymmetry in how the two kinds of secret are stored:
 *
 *   setup_tokens.token_hash    HASHED. A bearer token; we only ever need to
 *                              compare a presented value, never reproduce it.
 *
 *   website_credentials
 *     .secret_ciphertext       ENCRYPTED, not hashed. HMAC verification has to
 *                              recompute a signature from the plaintext secret,
 *                              so a one-way hash would make the scheme unusable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('owner_user_id')->constrained('users')->restrictOnDelete();

            // Normalized to lowercase host, no scheme, no trailing slash.
            $table->string('domain')->unique();
            $table->string('display_name')->nullable();
            $table->string('status', 20)->default('pending'); // pending | verified | suspended

            // The Stripe account every payment from this site routes to.
            $table->foreignUuid('stripe_account_id')->nullable()->constrained('stripe_accounts')->nullOnDelete();

            // Where the platform calls back to run the ownership challenge.
            $table->string('challenge_url')->nullable();

            $table->string('plugin_version', 20)->nullable();
            $table->string('wp_version', 20)->nullable();
            $table->string('php_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['owner_user_id']);
        });

        Schema::create('website_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('website_id')->constrained('websites')->cascadeOnDelete();

            // Public identifier the plugin sends in X-Usman-Key.
            $table->string('key_id', 64)->unique();
            $table->text('secret_ciphertext');

            $table->string('status', 20)->default('active'); // active | revoked
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
        });

        Schema::create('setup_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();

            // The domain this token is allowed to claim, if pre-declared.
            $table->string('intended_domain')->nullable();

            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignUuid('claimed_website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_tokens');
        Schema::dropIfExists('website_credentials');
        Schema::dropIfExists('websites');
    }
};
