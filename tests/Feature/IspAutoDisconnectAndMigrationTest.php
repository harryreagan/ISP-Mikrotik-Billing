<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\HotspotVoucher;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\Router;
use App\Services\MikrotikDuration;
use App\Services\Migration\ZeroDowntimeMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IspAutoDisconnectAndMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mikrotik_duration_parses_thirty_minutes_without_decimals(): void
    {
        $res1 = MikrotikDuration::parse('30 mins');
        $this->assertEquals(1800, $res1['seconds']);
        $this->assertEquals('30m', $res1['mikrotik']);

        $res2 = MikrotikDuration::parse(0.5, 'hours');
        $this->assertEquals(1800, $res2['seconds']);
        $this->assertEquals('30m', $res2['mikrotik']);

        $res3 = MikrotikDuration::parse('1 hour');
        $this->assertEquals(3600, $res3['seconds']);
        $this->assertEquals('1h', $res3['mikrotik']);

        $res4 = MikrotikDuration::parse('2 days');
        $this->assertEquals(172800, $res4['seconds']);
        $this->assertEquals('2d', $res4['mikrotik']);
    }

    public function test_expire_hotspot_users_command_executes_cleanly(): void
    {
        $exitCode = Artisan::call('app:expire-realtime-hotspot-users');
        $this->assertEquals(0, $exitCode);
    }

    public function test_zero_downtime_rsc_file_migration(): void
    {
        $tenantId = 1;
        $router = Router::create([
            'tenant_id' => $tenantId,
            'name'      => 'Test-Migrated-Router',
            'ip'        => '10.10.0.99',
            'status'    => 'online',
        ]);

        $rscContent = <<<RSC
# MikroTik RouterOS Script Export
/ppp secret
add comment="John Doe 0712345678" name="johndoe" password="secretpassword1" profile="10M-Unlimited" remote-address="10.0.0.50" service=pppoe
add comment="Alice 254799887766" name="alice" password="secretpassword2" profile="20M-Fast" remote-address="10.0.0.51" service=pppoe
/ip hotspot user
add comment="Guest 30m" limit-uptime=30m name="voucher30m" password="voucher30m" profile="default"
add comment="Guest 1h" limit-uptime=1h name="voucher1h" password="voucher1h" profile="default"
RSC;

        $service = app(ZeroDowntimeMigrationService::class);
        $result = $service->importRscFile($tenantId, $router->id, $rscContent);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['migrated_pppoe']);
        $this->assertEquals(2, $result['migrated_hotspot']);
        $this->assertTrue($result['zero_downtime']);

        // Verify customers and ppp secrets created
        $johnCust = CustomersInfo::where('customer_name', 'like', '%John Doe%')->first();
        $this->assertNotNull($johnCust);
        $this->assertEquals('0712345678', $johnCust->mobile);

        $johnSecret = PPPSecrets::where('username', 'johndoe')->first();
        $this->assertNotNull($johnSecret);
        $this->assertEquals('secretpassword1', $johnSecret->password);

        // Verify hotspot vouchers created with accurate duration
        $voucher30m = HotspotVoucher::where('username', 'voucher30m')->first();
        $this->assertNotNull($voucher30m);
        $this->assertEquals(1800, $voucher30m->uptime_limit);
        $this->assertEquals('30m', $voucher30m->validity_duration);
    }
}

