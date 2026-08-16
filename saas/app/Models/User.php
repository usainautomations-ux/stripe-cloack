<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A platform administrator has tenant_id === null and a platform role; every
 * other user belongs to exactly one tenant.
 *
 * Note that User deliberately does NOT use BelongsToTenant. Authentication has
 * to find a user before any tenant is known, so the global scope would make
 * login impossible. The RLS policy still covers this table — the auth path
 * elevates explicitly and narrowly (see TenantContext::withoutScope).
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'role_key',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            // Decrypted only when a TOTP challenge is actually being verified.
            'mfa_secret' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_key', 'key');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'owner_user_id');
    }

    /**
     * The Stripe accounts an admin has granted this user. This relation IS the
     * access-control boundary for money movement — not a convenience listing.
     */
    public function stripeAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            StripeAccount::class,
            'stripe_account_assignments',
            'user_id',
            'stripe_account_id'
        )->withTimestamps();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->tenant_id === null && $this->role?->is_platform_role === true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }

    /**
     * Permission check, resolved through the user's role.
     */
    public function hasPermission(string $permissionKey): bool
    {
        return $this->relationLoaded('role')
            ? $this->role->grants($permissionKey)
            : Role::find($this->role_key)?->grants($permissionKey) ?? false;
    }

    /**
     * Whether this user may transact against a given Stripe account.
     * Platform admins are not exempt: assignment is checked for everyone, so a
     * compromised admin session cannot silently route money through an account
     * nobody granted them.
     */
    public function canUseStripeAccount(string $stripeAccountId): bool
    {
        return $this->stripeAccounts()
            ->whereKey($stripeAccountId)
            ->exists();
    }
}
