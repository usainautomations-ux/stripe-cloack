<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'transaction_id',
        'stripe_refund_id',
        'amount_minor',
        'reason',
        'status',
        'initiated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * The person who authorized this refund. Null only for refunds Stripe
     * initiated on its own, such as a dispute resolving in the buyer's favour.
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
