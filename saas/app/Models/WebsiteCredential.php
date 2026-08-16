<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Crypto\SecretCipher;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SensitiveParameter;

/**
 * The credential pair a WordPress plugin authenticates with.
 *
 * The secret is ENCRYPTED rather than hashed, which is a deliberate departure
 * from how bearer tokens are normally stored. HMAC verification has to
 * recompute a signature over the request using the same secret the client
 * used — that requires the plaintext, which a one-way hash cannot give back.
 *
 * The trade-off is accepted because the blast radius is small by construction:
 * this secret authorizes creating orders for ONE website. It grants no access
 * to Stripe, to other tenants, or to any other site's credential.
 */
class WebsiteCredential extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'website_id',
        'key_id',
        'status',
    ];

    protected $hidden = ['secret_ciphertext'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setSecret(#[SensitiveParameter] string $plaintext): void
    {
        $this->secret_ciphertext = app(SecretCipher::class)->encrypt($plaintext);
    }

    public function secret(): string
    {
        return app(SecretCipher::class)->decrypt($this->secret_ciphertext);
    }

    public function revoke(): void
    {
        $this->status = 'revoked';
        $this->revoked_at = now();
        $this->save();
    }

    /**
     * Mint a fresh credential pair for a website.
     *
     * @return array{model: self, key_id: string, secret: string} The plaintext
     *         secret is returned exactly once, to be handed to the plugin and
     *         then forgotten by this side.
     */
    public static function issueFor(Website $website): array
    {
        $keyId = 'wsk_' . bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));

        $credential = new self([
            'tenant_id' => $website->tenant_id,
            'website_id' => $website->id,
            'key_id' => $keyId,
            'status' => 'active',
        ]);

        $credential->setSecret($secret);
        $credential->save();

        return ['model' => $credential, 'key_id' => $keyId, 'secret' => $secret];
    }
}
