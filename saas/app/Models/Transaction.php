<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'stripe_account_id',
        'type',
        'status',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'amount_minor',
        'currency',
        'failure_code',
        'failure_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
