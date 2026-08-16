<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function stripeAccounts(): HasMany
    {
        return $this->hasMany(StripeAccount::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
