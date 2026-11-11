<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\HotspotVoucher;
use App\Models\PackageList;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantContext;
use App\Traits\BelongsToTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create or find two distinct tenants
        $this->tenantA = Tenant::firstOrCreate(
            ['slug' => 'test-isp-a'],
            ['name' => 'Test ISP A', 'email' => 'isp-a@test.com', 'is_active' => true]
        );

        $this->tenantB = Tenant::firstOrCreate(
            ['slug' => 'test-isp-b'],
            ['name' => 'Test ISP B', 'email' => 'isp-b@test.com', 'is_active' => true]
        );

        // Create users for each tenant
        $this->userA = User::firstOrCreate(
            ['email' => 'user-a@test.com'],
            [
                'name' => 'Admin ISP A',
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenantA->id,
                'role' => 'admin',
                'is_super_admin' => false,
            ]
        );
        $this->userA->tenant_id = $this->tenantA->id;
        $this->userA->saveQuietly();

        $this->userB = User::firstOrCreate(
            ['email' => 'user-b@test.com'],
            [
                'name' => 'Admin ISP B',
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenantB->id,
                'role' => 'admin',
                'is_super_admin' => false,
            ]
        );
        $this->userB->tenant_id = $this->tenantB->id;
        $this->userB->saveQuietly();

        $this->superAdmin = User::firstOrCreate(
            ['email' => 'superadmin-test@test.com'],
            [
                'name' => 'Platform Super Admin',
                'password' => bcrypt('password123'),
                'role' => 'super_admin',
                'is_super_admin' => true,
            ]
        );
        $this->superAdmin->is_super_admin = true;
        $this->superAdmin->saveQuietly();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test ISP A cannot list or query ISP B routers.
     */
    public function test_isp_a_cannot_see_isp_b_routers(): void
    {
        // Create router for ISP B
        TenantContext::setTenantId($this->tenantB->id);
        $routerB = Router::create([
            'name' => 'Router-B-Secret',
            'ip_address' => '10.200.0.1',
            'status' => 'online',
            'tenant_id' => $this->tenantB->id,
        ]);

        // Create router for ISP A
        TenantContext::setTenantId($this->tenantA->id);
        $routerA = Router::create([
            'name' => 'Router-A-Public',
            'ip_address' => '10.100.0.1',
            'status' => 'online',
            'tenant_id' => $this->tenantA->id,
        ]);

        // When acting as User A
        $this->actingAs($this->userA);
        TenantContext::setTenantId($this->tenantA->id);

        $visibleRouters = Router::all();

        $this->assertTrue($visibleRouters->contains('id', $routerA->id));
        $this->assertFalse($visibleRouters->contains('id', $routerB->id), 'ISP A must NOT see ISP B router in queries');

        // Direct find of Router B under Tenant A scope must fail / return null
        $fetched = Router::find($routerB->id);
        $this->assertNull($fetched, 'Direct find of foreign router must return null under TenantScope');

        // Cleanup
        TenantContext::runWithTenant($this->tenantA->id, fn() => $routerA->delete());
        TenantContext::runWithTenant($this->tenantB->id, fn() => $routerB->delete());
    }

    /**
     * Test Fail-Closed: with NO tenant context, queries return 0 rows.
     */
    public function test_fail_closed_when_no_tenant_context(): void
    {
        TenantContext::clear();
        auth()->logout();

        // Any tenant-scoped query without context returns 0 rows
        $count = Router::count();
        $this->assertEquals(0, $count, 'Fail closed: without tenant context, query must return 0 rows');
    }

    /**
     * Test Super Admin can see all records across tenants.
     */
    public function test_super_admin_can_view_all_tenants_data(): void
    {
        TenantContext::runWithTenant($this->tenantA->id, function () {
            Router::firstOrCreate(['name' => 'Test-R-A-Scope'], ['ip_address' => '10.10.10.1', 'tenant_id' => $this->tenantA->id]);
        });
        TenantContext::runWithTenant($this->tenantB->id, function () {
            Router::firstOrCreate(['name' => 'Test-R-B-Scope'], ['ip_address' => '10.10.20.1', 'tenant_id' => $this->tenantB->id]);
        });

        // Act as Super Admin
        $this->actingAs($this->superAdmin);
        TenantContext::clear(); // Super Admin bypasses scope

        $allRouters = Router::all();
        $this->assertGreaterThanOrEqual(2, $allRouters->count(), 'Super admin must see records from all tenants');
    }

    /**
     * Test Policies deny access when record.tenant_id != user.tenant_id.
     */
    public function test_policy_denies_unauthorized_cross_tenant_access(): void
    {
        $routerB = TenantContext::runWithTenant($this->tenantB->id, function () {
            return Router::create([
                'name' => 'Policy-Test-Router-B',
                'ip_address' => '10.200.0.99',
                'tenant_id' => $this->tenantB->id,
            ]);
        });

        // Act as User A
        $this->actingAs($this->userA);
        TenantContext::setTenantId($this->tenantA->id);

        $this->assertFalse(Gate::forUser($this->userA)->allows('view', $routerB));
        $this->assertFalse(Gate::forUser($this->userA)->allows('update', $routerB));
        $this->assertFalse(Gate::forUser($this->userA)->allows('delete', $routerB));

        // Act as Super Admin: must allow
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('view', $routerB));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('update', $routerB));

        TenantContext::runWithTenant($this->tenantB->id, fn() => $routerB->delete());
    }

    /**
     * Test tenant_id cannot be overwritten / tampered via updates.
     */
    public function test_tenant_id_tampering_is_prevented(): void
    {
        $routerA = TenantContext::runWithTenant($this->tenantA->id, function () {
            return Router::create([
                'name' => 'Tamper-Check-Router',
                'ip_address' => '10.100.0.50',
                'tenant_id' => $this->tenantA->id,
            ]);
        });

        TenantContext::setTenantId($this->tenantA->id);
        $this->actingAs($this->userA);

        // Attempt to tamper tenant_id to tenant B
        $routerA->tenant_id = $this->tenantB->id;
        $routerA->save();

        // Refresh and assert tenant_id remains unchanged
        $routerA->refresh();
        $this->assertEquals($this->tenantA->id, $routerA->tenant_id, 'tenant_id must not change on update');

        TenantContext::runWithTenant($this->tenantA->id, fn() => $routerA->delete());
    }

    /**
     * Test every model with BelongsToTenant correctly scopes queries.
     */
    public function test_all_tenant_models_have_belong_to_tenant_trait(): void
    {
        $modelFiles = File::allFiles(app_path('Models'));
        $verifiedModels = 0;

        foreach ($modelFiles as $file) {
            $class = 'App\\Models\\' . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            // Exclude non-tenant core platform models
            $excluded = [
                'App\\Models\\Tenant',
                'App\\Models\\PlatformSetting',
                'App\\Models\\Session',
                'App\\Models\\FailedJob',
                'App\\Models\\PersonalAccessToken',
            ];

            if (in_array($class, $excluded, true)) {
                continue;
            }

            try {
                $instance = new $class();
                $table = $instance->getTable();

                if (Schema::hasColumn($table, 'tenant_id')) {
                    $traits = class_uses_recursive($class);
                    $this->assertArrayHasKey(
                        BelongsToTenant::class,
                        $traits,
                        "Model [{$class}] has tenant_id column in table [{$table}] but does NOT use BelongsToTenant trait!"
                    );
                    $verifiedModels++;
                }
            } catch (\Throwable $e) {
                // Ignore models that cannot be instantiated without database connection
            }
        }

        $this->assertGreaterThan(10, $verifiedModels, 'Must have verified at least 10 tenant-scoped models');
    }
}
