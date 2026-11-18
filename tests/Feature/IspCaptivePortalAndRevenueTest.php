<?php

namespace Tests\Feature;

use App\Models\HotspotSale;
use App\Models\CollectionSummary;
use App\Models\Customer;
use App\Models\HotspotPackage;
use App\Models\HotspotVoucher;
use App\Models\User;
use App\Services\CaptivePortalTemplates;
use App\Services\IspRevenueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IspCaptivePortalAndRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_eight_captive_portal_templates_render_successfully(): void
    {
        $templates = CaptivePortalTemplates::TEMPLATES;
        $this->assertCount(8, $templates);

        $mockPackages = [
            (object)[
                'id' => 1,
                'name' => '1 Hour High Speed',
                'price' => 10,
                'duration' => 1,
                'duration_unit' => 'hours',
                'speed_limit' => '10M/10M',
                'description' => 'Fast browsing',
                'formatted_duration' => '1 Hour',
            ]
        ];

        foreach (array_keys($templates) as $templateKey) {
            $html = CaptivePortalTemplates::render(
                template: $templateKey,
                packages: $mockPackages,
                siteName: 'Test WiFi Network',
                currency: 'KES',
                primaryColor: '#0ea5e9',
                logoUrl: null,
                hotspotLoginUrl: 'http://hotspot.hsmart.lan/login',
                linkOrig: 'http://example.com'
            );

            $this->assertNotEmpty($html, "Template {$templateKey} rendered empty output.");
            $this->assertStringContainsString('Test WiFi Network', $html);
            $this->assertStringContainsString('1 Hour High Speed', $html);
            $this->assertStringContainsString('/api/payment/stk-push', $html);
            $this->assertStringContainsString('/api/portal/returning-user', $html);
        }
    }

    public function test_returning_user_api_validates_required_parameters(): void
    {
        $response = $this->postJson('/api/portal/returning-user', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['identifier']);
    }

    public function test_returning_user_api_returns_404_when_no_active_session_or_voucher(): void
    {
        $response = $this->postJson('/api/portal/returning-user', [
            'identifier' => '0712345678',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_returning_user_api_authenticates_valid_unexpired_voucher(): void
    {
        $voucher = HotspotVoucher::create([
            'tenant_id' => 1,
            'code' => 'TESTVOUCHER123',
            'price' => 20.00,
            'duration_hours' => 2,
            'uptime_limit' => 7200,
            'status' => 'unused',
        ]);

        $response = $this->postJson('/api/portal/returning-user', [
            'identifier' => 'TESTVOUCHER123',
            'mac_address' => '11:22:33:44:55:66',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'username' => 'TESTVOUCHER123',
            'type' => 'voucher',
        ]);
    }

    public function test_returning_user_api_authenticates_valid_unexpired_sale_by_phone(): void
    {
        $sale = HotspotSale::create([
            'tenant_id' => 1,
            'phone' => '0799887766',
            'amount' => 50.00,
            'duration_hours' => 24,
            'status' => 'completed',
            'username' => 'hs_0799887766',
            'password' => 'secret123',
            'expires_at' => Carbon::now()->addHours(12),
        ]);

        $response = $this->postJson('/api/portal/returning-user', [
            'identifier' => '0799887766',
            'mac_address' => 'AA:BB:CC:11:22:33',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'username' => 'hs_0799887766',
            'type' => 'active_sale',
        ]);
    }

    public function test_check_session_api_returns_status(): void
    {
        $response = $this->getJson('/api/portal/check-session?mac=AA:BB:CC:DD:EE:FF');
        $response->assertStatus(200);
        $response->assertJsonStructure(['active']);
    }

    public function test_revenue_service_monthly_reconciliation_and_tenant_isolation(): void
    {
        $tenantA = 101;
        $tenantB = 102;
        $year = 2026;

        // Tenant A: 2 hotspot sales in March 2026
        HotspotSale::create([
            'tenant_id' => $tenantA,
            'phone' => '0700000001',
            'amount' => 100.00,
            'status' => 'completed',
            'created_at' => Carbon::create($year, 3, 15, 10, 0, 0),
        ]);
        HotspotSale::create([
            'tenant_id' => $tenantA,
            'phone' => '0700000002',
            'amount' => 150.00,
            'status' => 'completed',
            'created_at' => Carbon::create($year, 3, 20, 14, 0, 0),
        ]);

        // Tenant A: 1 PPPoE payment in May 2026
        CollectionSummary::create([
            'tenant_id' => $tenantA,
            'customer_id' => 5001,
            'payment_type' => 'mpesa',
            'amount' => 500.00,
            'created_at' => Carbon::create($year, 5, 10, 9, 0, 0),
        ]);

        // Tenant B: 1 hotspot sale in March 2026 (Should NEVER leak into Tenant A)
        HotspotSale::create([
            'tenant_id' => $tenantB,
            'phone' => '0700000003',
            'amount' => 300.00,
            'status' => 'completed',
            'created_at' => Carbon::create($year, 3, 10, 12, 0, 0),
        ]);

        // Calculate for Tenant A
        $reportA = IspRevenueService::getRevenueReport($tenantA, $year);

        // March for Tenant A should be exactly 250.00
        $marchA = collect($reportA['months'])->firstWhere('month', 3);
        $this->assertEquals(250.00, $marchA['hotspot']);
        $this->assertEquals(0.00, $marchA['pppoe']);
        $this->assertEquals(250.00, $marchA['total']);

        // May for Tenant A should be exactly 500.00
        $mayA = collect($reportA['months'])->firstWhere('month', 5);
        $this->assertEquals(0.00, $mayA['hotspot']);
        $this->assertEquals(500.00, $mayA['pppoe']);
        $this->assertEquals(500.00, $mayA['total']);

        // Annual total for Tenant A should be exactly 750.00
        $this->assertEquals(250.00, $reportA['summary']['hotspot']);
        $this->assertEquals(500.00, $reportA['summary']['pppoe']);
        $this->assertEquals(750.00, $reportA['summary']['total']);

        // Sum of all 12 months must exactly equal annual summary total
        $sumOfMonths = collect($reportA['months'])->sum('total');
        $this->assertEquals($reportA['summary']['total'], $sumOfMonths);

        // Super Admin (tenant_id = null)
        $superReport = IspRevenueService::getRevenueReport(null, $year);
        // Total should be Tenant A (750) + Tenant B (300) = 1050
        $this->assertEquals(550.00, $superReport['summary']['hotspot']); // 250 + 300
        $this->assertEquals(500.00, $superReport['summary']['pppoe']);
        $this->assertEquals(1050.00, $superReport['summary']['total']);
    }

    public function test_artisan_reconcile_revenue_command_executes_cleanly(): void
    {
        $exitCode = Artisan::call('isp:reconcile-revenue', [
            '--year' => 2026,
        ]);

        $this->assertEquals(0, $exitCode);
    }
}

