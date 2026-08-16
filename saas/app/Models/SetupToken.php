<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short-lived, single-use token that lets a freshly installed plugin claim
 * its credentials.
 *
 * Unlike the website credential secret, this one IS hashed: it is a pure bearer
 * token, so we only ever need to compare a presented value against the stored
 * digest, never reproduce the original.
 */
class SetupToken extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    /**
     * Deliberately short. The operator generates the token in the dashboard and
     * pastes it into the plugin within the same sitting; anything longer is
     * just a wider window for a token sitting in a clipboard or a chat log.
     */
    public const TTL_MINUTES = 15;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'intended_domain',
        'token_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function claimedWebsite(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'claimed_website_id');
    }

    public function isRedeemable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Hash a presented token for lookup. SHA-256 rather than bcrypt is correct
     * here: the token is 256 bits of machine-generated randomness, so there is
     * no dictionary to slow down, and lookups happen by digest equality.
     */
    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Issue a token. The plaintext is returned once and never stored.
     *
     * @return array{model: self, token: string}
     */
    public static function issue(User $creator, ?string $intendedDomain = null): array
    {
        $plaintext = 'ust_' . bin2hex(random_bytes(24));

        $token = self::create([
            'tenant_id' => $creator->tenant_id,
            'created_by_user_id' => $creator->id,
            'intended_domain' => $intendedDomain ? Website::normalizeDomain($intendedDomain) : null,
            'token_hash' => self::hash($plaintext),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return ['model' => $token, 'token' => $plaintext];
    }
}
