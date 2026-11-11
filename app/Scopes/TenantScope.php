<?php

namespace App\Scopes;

use App\Services\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    protected static bool $resolving = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        // 1. Prevent recursion during auth resolution or nested tenant checks
        if (self::$resolving) {
            return;
        }

        // 2. Never scope User queries during Auth lookups unless tenant explicitly configured
        if ($model instanceof \App\Models\User && !TenantContext::isTenantExplicitlySet()) {
            return;
        }

        self::$resolving = true;
        try {
            // 3. If user is Super Admin and no specific tenant explicitly configured, bypass scope
            if (TenantContext::isSuperAdmin() && !TenantContext::isTenantExplicitlySet()) {
                return;
            }

            $tenantId = TenantContext::getTenantId();

            // 4. FAIL CLOSED: If no tenant context exists, return NO rows (1 = 0)
            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            // 5. Normal tenant scoping
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        } finally {
            self::$resolving = false;
        }
    }
}
