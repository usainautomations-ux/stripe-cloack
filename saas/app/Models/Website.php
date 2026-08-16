<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'owner_user_id',
        'domain',
        'display_name',
        'status',
        'stripe_account_id',
        'challenge_url',
        'plugin_version',
        'wp_version',
        'php_version',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(WebsiteCredential::class);
    }

    public function activeCredential(): HasOne
    {
        return $this->hasOne(WebsiteCredential::class)->where('status', 'active');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Whether this site is in a state where it may create payments: verified,
     * not suspended, and bound to a usable Stripe account.
     */
    public function canAcceptPayments(): bool
    {
        return $this->isVerified()
            && $this->stripe_account_id !== null
            && $this->stripeAccount?->isUsable() === true;
    }

    /**
     * Reduce any user-supplied URL to a bare lowercase host, so that
     * "https://Example.com/", "example.com" and "http://example.com" cannot
     * register as three separate websites.
     */
    public static function normalizeDomain(string $input): string
    {
        $trimmed = trim($input);
        $host = parse_url(
            str_contains($trimmed, '://') ? $trimmed : "https://{$trimmed}",
            PHP_URL_HOST
        );

        return strtolower($host ?: $trimmed);
    }
}
