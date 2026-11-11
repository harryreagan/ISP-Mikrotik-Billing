import subprocess

test_code = """
use App\\Models\\Router;
use App\\Models\\User;
use App\\Services\\Tenant\\TenantContext;

// 1. Fail-closed test
TenantContext::clear();
auth()->logout();
$failClosedCount = Router::count();
echo 'FAIL_CLOSED_COUNT=' . $failClosedCount . PHP_EOL;

// 2. Tenant 1 scope test
TenantContext::setTenantId(1);
$t1Count = Router::count();
echo 'TENANT_1_ROUTERS=' . $t1Count . PHP_EOL;

// 3. Foreign router find test under Tenant 1
$t2Router = Router::withoutGlobalScopes()->where('tenant_id', '!=', 1)->first();
if ($t2Router) {
    $found = Router::find($t2Router->id);
    echo 'CROSS_TENANT_FIND_RESULT=' . ($found ? 'LEAKED' : 'BLOCKED_NULL') . PHP_EOL;
} else {
    echo 'CROSS_TENANT_FIND_RESULT=NO_OTHER_TENANT_ROUTER' . PHP_EOL;
}

// 4. Super admin bypass test
$super = User::where('is_super_admin', 1)->orWhere('role', 'super_admin')->first();
if ($super) {
    auth()->login($super);
    TenantContext::clear();
    $allCount = Router::count();
    echo 'SUPER_ADMIN_ALL_COUNT=' . $allCount . PHP_EOL;
}
"""

clean_code = " ".join([line.strip() for line in test_code.splitlines() if line.strip() and not line.strip().startswith("//")])
remote_cmd = f"cd /var/www/poss/isp-billing && php artisan tinker --execute=\"{clean_code}\""

res = subprocess.run(
    ["ssh", "-o", "StrictHostKeyChecking=no", "root@213.199.49.216", remote_cmd],
    capture_output=True,
    text=True
)
print("STDOUT:")
print(res.stdout)
print("STDERR:")
print(res.stderr)
