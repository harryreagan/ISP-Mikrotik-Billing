<?php

namespace App\Services\Tenant;

use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TenantContext
{
    protected static ?int $currentTenantId = null;
    protected static bool $explicitlySet = false;

    /**
     * Get the active tenant ID for the current execution context.
     */
    public static function getTenantId(): ?int
    {
        // 1. Explicitly overridden tenant ID (e.g. inside a queued job or CLI command)
        if (self::$explicitlySet) {
            return self::$currentTenantId;
        }

        // 2. Check authenticated user
        if (Auth::check()) {
            $user = Auth::user();

            // If super admin and no specific tenant selected, return null (global scope bypassed)
            if (self::isSuperAdmin($user)) {
                $sessionTenant = session('impersonated_tenant_id') ?: session('tenant_id');
                return $sessionTenant ? (int) $sessionTenant : null;
            }

            if (!empty($user->tenant_id)) {
                return (int) $user->tenant_id;
            }
        }

        // 3. Check session fallback
        $sessionTenant = session('tenant_id') ?: session('impersonated_tenant_id');
        if (!empty($sessionTenant)) {
            return (int) $sessionTenant;
        }

        return null;
    }

    /**
     * Set the current tenant explicitly.
     */
    public static function setTenantId(?int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
        self::$explicitlySet = true;
    }

    /**
     * Clear explicitly set tenant.
     */
    public static function clear(): void
    {
        self::$currentTenantId = null;
        self::$explicitlySet = false;
    }

    /**
     * Check if a tenant has been explicitly configured in memory.
     */
    public static function isTenantExplicitlySet(): bool
    {
        return self::$explicitlySet;
    }

    /**
     * Determine if a user (or current logged-in user) is a Super Admin.
     */
    public static function isSuperAdmin(?User $user = null): bool
    {
        $target = $user ?? Auth::user();
        if (!$target) {
            return false;
        }

        if (!empty($target->is_super_admin)) {
            return true;
        }

        if (strtolower($target->role ?? '') === 'superadmin' || strtolower($target->role ?? '') === 'super_admin') {
            return true;
        }

        if (method_exists($target, 'hasRole') && $target->hasRole('Super Admin')) {
            return true;
        }

        return false;
    }

    /**
     * Run a callback inside a specific tenant context and restore previous context.
     */
    public static function runWithTenant(int $tenantId, callable $callback)
    {
        $previousTenantId = self::$currentTenantId;
        $previousExplicit = self::$explicitlySet;

        self::setTenantId($tenantId);

        try {
            return $callback();
        } finally {
            self::$currentTenantId = $previousTenantId;
            self::$explicitlySet = $previousExplicit;
        }
    }

    /**
     * Resolve tenant from a router's unique random provisioning token.
     */
    public static function resolveFromRouterToken(string $token): ?int
    {
        if (empty($token)) {
            return null;
        }

        try {
            $router = Router::withoutGlobalScopes()
                ->where('setup_token', $token)
                ->orWhere('provision_token', $token)
                ->first();

            return $router?->tenant_id ? (int) $router->tenant_id : null;
        } catch (\Throwable $e) {
            Log::warning("[TenantContext] resolveFromRouterToken failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve tenant from NAS IP / Router VPN IP.
     */
    public static function resolveFromNasIp(string $nasIp): ?int
    {
        if (empty($nasIp)) {
            return null;
        }

        try {
            $router = Router::withoutGlobalScopes()
                ->where('vpn_ip', $nasIp)
                ->orWhere('ip', $nasIp)
                ->first();

            return $router?->tenant_id ? (int) $router->tenant_id : null;
        } catch (\Throwable $e) {
            Log::warning("[TenantContext] resolveFromNasIp failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate tenant-scoped cache key.
     */
    public static function cacheKey(string $key, ?int $tenantId = null): string
    {
        $tid = $tenantId ?? self::getTenantId() ?? 0;
        return "tenant_{$tid}_{$key}";
    }

    /**
     * Generate tenant-scoped storage directory path.
     */
    public static function storagePath(string $path, ?int $tenantId = null): string
    {
        $tid = $tenantId ?? self::getTenantId() ?? 0;
        return "tenants/{$tid}/" . ltrim($path, '/');
    }
}

