<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Tenant\TenantContext;

abstract class TenantModelPolicy
{
    /**
     * Super Admin bypasses all model-level authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (TenantContext::isSuperAdmin($user)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return !empty($user->tenant_id);
    }

    public function view(User $user, $model): bool
    {
        return !empty($user->tenant_id) && (int) $user->tenant_id === (int) $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return !empty($user->tenant_id);
    }

    public function update(User $user, $model): bool
    {
        return !empty($user->tenant_id) && (int) $user->tenant_id === (int) $model->tenant_id;
    }

    public function delete(User $user, $model): bool
    {
        return !empty($user->tenant_id) && (int) $user->tenant_id === (int) $model->tenant_id;
    }
}
