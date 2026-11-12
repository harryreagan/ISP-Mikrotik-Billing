<?php

namespace Tests\Feature;

use App\Models\IspSmsGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Sms\SmsBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsBalanceTest extends TestCase
{
    public function test_unauthenticated_user_cannot_access_sms_balance(): void
    {
        $response = $this->getJson('/api/sms/balance');
        $response->assertStatus(401);
    }

    public function test_tenant_with_no_gateway_returns_status_none(): void
    {
        $user = new User(['id' => 9999, 'name' => 'Test User', 'email' => 'test@example.com']);
        $user->tenant_id = 9999;

        $this->actingAs($user);

        $response = $this->getJson('/api/sms/balance');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'none',
            'provider' => 'None',
            'balance' => 0,
        ]);
    }

    public function test_service_parses_africas_talking_balance(): void
    {
        Http::fake([
            'https://api.africastalking.com/*' => Http::response([
                'UserData' => [
                    'balance' => 'KES 450.50',
                ],
            ], 200),
        ]);

        $service = new SmsBalanceService();
        $gateway = new IspSmsGateway([
            'tenant_id' => 1,
            'gateway_name' => 'africas_talking',
            'credentials' => [
                'username' => 'test_user',
                'api_key' => 'test_key',
            ],
            'is_active' => true,
            'low_balance_threshold' => 100,
        ]);

        $res = $service->fetchLiveBalance($gateway);

        $this->assertEquals('ok', $res['status']);
        $this->assertEquals("Africa's Talking", $res['provider']);
        $this->assertEquals(450.50, $res['balance']);
    }

    public function test_service_detects_low_balance(): void
    {
        Http::fake([
            'https://api.africastalking.com/*' => Http::response([
                'UserData' => [
                    'balance' => 'KES 45.00',
                ],
            ], 200),
        ]);

        $service = new SmsBalanceService();
        $gateway = new IspSmsGateway([
            'tenant_id' => 1,
            'gateway_name' => 'africas_talking',
            'credentials' => [
                'username' => 'test_user',
                'api_key' => 'test_key',
            ],
            'is_active' => true,
            'low_balance_threshold' => 100,
        ]);

        $res = $service->fetchLiveBalance($gateway);

        $this->assertEquals('low', $res['status']);
        $this->assertEquals(45.00, $res['balance']);
    }

    public function test_service_detects_empty_balance(): void
    {
        Http::fake([
            'https://api.africastalking.com/*' => Http::response([
                'UserData' => [
                    'balance' => 'KES 0.00',
                ],
            ], 200),
        ]);

        $service = new SmsBalanceService();
        $gateway = new IspSmsGateway([
            'tenant_id' => 1,
            'gateway_name' => 'africas_talking',
            'credentials' => [
                'username' => 'test_user',
                'api_key' => 'test_key',
            ],
            'is_active' => true,
            'low_balance_threshold' => 100,
        ]);

        $res = $service->fetchLiveBalance($gateway);

        $this->assertEquals('empty', $res['status']);
        $this->assertEquals(0.00, $res['balance']);
    }
}

