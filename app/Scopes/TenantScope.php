<?php

namespace App\Scopes;

use App\Services\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        // 1. If user is Super Admin and no specific tenant explicitly configured, bypass scope
        if (TenantContext::isSuperAdmin() && !TenantContext::isTenantExplicitlySet()) {
            return;
        }

        // 2. Allow unauthenticated login/authentication lookups on User model
        if ($model instanceof \App\Models\User && !\Illuminate\Support\Facades\Auth::check() && !TenantContext::isTenantExplicitlySet()) {
            return;
        }

        $tenantId = TenantContext::getTenantId();

        // 3. FAIL CLOSED: If no tenant context exists, return NO rows (1 = 0)
        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        // 3. Normal tenant scoping
        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
