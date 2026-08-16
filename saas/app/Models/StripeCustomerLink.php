<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps one of our customers to their Stripe customer id on a specific account.
 *
 * Stripe customer ids are scoped per account, so the same buyer transacting
 * against two of our Stripe accounts legitimately has two ids.
 */
class StripeCustomerLink extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'stripe_account_id',
        'stripe_customer_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }
}
