<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;

/**
 * Application-layer half of tenant isolation.
 *
 * This is belt; the RLS policies in the database are braces. Either one alone
 * would stop a cross-tenant read — the pair exists so that a bug in one is not
 * a breach, and the global scope is what keeps ordinary queries from silently
 * returning empty results when RLS is doing its job.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $context = app(TenantContext::class);

            if ($context->hasBypass()) {
                return;
            }

            if ($tenantId = $context->tenantId()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Stamp the tenant on insert so callers cannot forget, and cannot lie.
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(TenantContext::class)->tenantId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
