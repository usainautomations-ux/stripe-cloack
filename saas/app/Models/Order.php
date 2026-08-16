<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'website_id',
        'stripe_account_id',
        'customer_id',
        'order_ref',
        'amount_minor',
        'currency',
        'status',
        'description',
        'return_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'amount_refunded_minor' => 'integer',
            'metadata' => 'array',
            'checkout_expires_at' => 'datetime',
            'checkout_consumed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * How much of this order can still be sent back. Guards against a sequence
     * of partial refunds summing past the captured amount.
     */
    public function refundableAmountMinor(): int
    {
        return max(0, $this->amount_minor - $this->amount_refunded_minor);
    }

    public function amountForHumans(): string
    {
        return number_format($this->amount_minor / 100, 2) . ' ' . strtoupper($this->currency);
    }
}
