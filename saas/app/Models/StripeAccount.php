<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Crypto\SecretCipher;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use SensitiveParameter;

/**
 * A Stripe account whose API credentials this platform holds directly.
 *
 * $hidden is not cosmetic here: it is the last line of defence against a
 * ciphertext column being serialized into a JSON response by an endpoint that
 * returned a model without thinking. The plaintext is only ever reachable
 * through secretKey(), which is called from exactly one place — the Stripe
 * client factory.
 */
class StripeAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'label',
        'mode',
        'status',
        'publishable_key',
        'statement_descriptor',
    ];

    protected $hidden = [
        'secret_key_ciphertext',
        'webhook_secret_ciphertext',
    ];

    protected function casts(): array
    {
        return ['key_rotated_at' => 'datetime'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StripeAccountAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'stripe_account_assignments',
            'stripe_account_id',
            'user_id'
        )->withTimestamps();
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->secret_key_ciphertext !== null;
    }

    /**
     * Seal a new secret key. Also records the last four characters so the
     * dashboard has something to display without ever decrypting.
     */
    public function setSecretKey(#[SensitiveParameter] string $plaintext): void
    {
        $cipher = app(SecretCipher::class);

        $this->secret_key_ciphertext = $cipher->encrypt($plaintext);
        $this->secret_key_last4 = $cipher->fingerprint($plaintext);
        $this->key_rotated_at = now();
    }

    public function setWebhookSecret(#[SensitiveParameter] string $plaintext): void
    {
        $this->webhook_secret_ciphertext = app(SecretCipher::class)->encrypt($plaintext);
    }

    /**
     * Recover the plaintext secret key. Call sites should treat the return value
     * as request-scoped and must never log, cache, or return it.
     */
    public function secretKey(): string
    {
        return app(SecretCipher::class)->decrypt($this->secret_key_ciphertext);
    }

    public function webhookSecret(): ?string
    {
        return $this->webhook_secret_ciphertext
            ? app(SecretCipher::class)->decrypt($this->webhook_secret_ciphertext)
            : null;
    }

    /**
     * What the dashboard shows in place of the key.
     */
    public function maskedSecretKey(): string
    {
        $prefix = $this->isLive() ? 'sk_live_' : 'sk_test_';

        return $prefix . '••••' . $this->secret_key_last4;
    }
}
