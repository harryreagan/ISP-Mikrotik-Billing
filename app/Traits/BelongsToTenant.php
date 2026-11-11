<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for the model.
     */
    public static function bootBelongsToTenant(): void
    {
        // 1. Attach global tenant isolation scope
        static::addGlobalScope(new TenantScope);

        // 2. Auto-fill tenant_id on creating hook
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = TenantContext::getTenantId();
                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });

        // 3. Prevent tenant_id tampering on updates unless Super Admin
        static::updating(function ($model) {
            if ($model->isDirty('tenant_id') && !TenantContext::isSuperAdmin()) {
                $model->tenant_id = $model->getOriginal('tenant_id');
            }
        });
    }

    /**
     * Relationship to the owning Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
