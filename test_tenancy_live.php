<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Router;
use App\Models\User;
use App\Services\Tenant\TenantContext;

echo "=== TENANCY ISOLATION VERIFICATION ===" . PHP_EOL;

// 1. Fail-closed test: With NO tenant context and NO logged-in user, count must be 0
TenantContext::clear();
auth()->logout();
$failClosed = Router::count();
echo "[TEST 1] Fail-closed count: " . $failClosed . " (Expected: 0) -> " . ($failClosed === 0 ? "PASSED" : "FAILED") . PHP_EOL;

// 2. Total active non-deleted routers across all tenants
$allRoutersCount = Router::withoutGlobalScope(\App\Scopes\TenantScope::class)->count();
echo "[INFO] Total active routers in system (deleted_at is null): " . $allRoutersCount . PHP_EOL;

// 3. Tenant 1 scope: Only see Tenant 1 routers
TenantContext::setTenantId(1);
$tenant1Routers = Router::all();
$allBelongToT1 = $tenant1Routers->every(fn($r) => (int)$r->tenant_id === 1);
echo "[TEST 2] Tenant 1 router count: " . $tenant1Routers->count() . ", all belong to Tenant 1? " . ($allBelongToT1 ? "YES (PASSED)" : "NO (FAILED)") . PHP_EOL;

// 4. Cross-tenant access attempt: Trying to find a router that belongs to another tenant under Tenant 1 context
$foreignRouter = Router::withoutGlobalScopes()->where('tenant_id', '!=', 1)->first();
if ($foreignRouter) {
    $leakAttempt = Router::find($foreignRouter->id);
    echo "[TEST 3] Cross-tenant Router::find(" . $foreignRouter->id . "): " . ($leakAttempt === null ? "BLOCKED NULL (PASSED)" : "LEAKED (FAILED)") . PHP_EOL;
} else {
    // Create a temporary foreign router for Tenant 99999 to test
    $tempRouter = Router::withoutGlobalScopes()->create([
        'name' => 'Isolation-Probe-Router',
        'ip_address' => '10.250.250.1',
        'tenant_id' => 99999,
        'status' => 'offline',
    ]);
    TenantContext::setTenantId(1);
    $leakAttempt = Router::find($tempRouter->id);
    echo "[TEST 3] Cross-tenant Router::find(" . $tempRouter->id . "): " . ($leakAttempt === null ? "BLOCKED NULL (PASSED)" : "LEAKED (FAILED)") . PHP_EOL;
    $tempRouter->delete();
}

// 5. Super admin bypass test: When super admin is authenticated, all routers visible
$superAdmin = User::withoutGlobalScopes()->where('is_super_admin', 1)->orWhere('role', 'super_admin')->first();
if (!$superAdmin) {
    // If no user has super admin flag, set on user 1 or create one for test
    $superAdmin = User::withoutGlobalScopes()->find(1);
    if ($superAdmin) {
        $superAdmin->is_super_admin = 1;
        $superAdmin->saveQuietly();
    }
}

if ($superAdmin) {
    auth()->login($superAdmin);
    auth()->setUser($superAdmin);
    TenantContext::clear();
    echo "[INFO] Testing Super Admin ID: " . $superAdmin->id . " email: " . $superAdmin->email . " is_super: " . $superAdmin->is_super_admin . " role: " . $superAdmin->role . PHP_EOL;
    echo "[INFO] TenantContext::isSuperAdmin(): " . (TenantContext::isSuperAdmin() ? "TRUE" : "FALSE") . PHP_EOL;
    echo "[INFO] TenantContext::isTenantExplicitlySet(): " . (TenantContext::isTenantExplicitlySet() ? "TRUE" : "FALSE") . PHP_EOL;
    echo "[INFO] Router toSql: " . Router::toSql() . " bindings: " . json_encode(Router::getBindings()) . PHP_EOL;
    $superAdminCount = Router::count();
    echo "[TEST 4] Super Admin visible routers: " . $superAdminCount . " vs total: " . $allRoutersCount . " -> " . ($superAdminCount === $allRoutersCount ? "PASSED" : "FAILED") . PHP_EOL;
} else {
    echo "[TEST 4] Super admin user not found in database to test bypass." . PHP_EOL;
}

echo "=== VERIFICATION COMPLETE ===" . PHP_EOL;
