<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopper, scoped to the website they bought from.
 *
 * Customers are intentionally NOT deduplicated across websites even when the
 * email matches. Two sites belonging to different employees should not have
 * their buyer lists silently merged into a shared profile.
 */
class Customer extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'website_id',
        'email',
        'name',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stripeLinks(): HasMany
    {
        return $this->hasMany(StripeCustomerLink::class);
    }
}
