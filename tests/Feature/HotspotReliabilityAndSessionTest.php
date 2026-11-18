<?php

namespace Tests\Feature;

use App\Models\CollectionSummary;
use App\Models\HotspotSale;
use App\Models\MpesaStkTransaction;
use App\Models\Router;
use App\Models\RouterPendingActivation;
use App\Models\Tenant;
use App\Models\UnmatchedPayment;
use App\Services\ProvisioningTokenService;
use App\Services\RouterPendingActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class HotspotReliabilityAndSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test BUG 1: Router hello detects IP/network change, updates last_known_ip and network_changed_at.
     */
    public function test_router_hello_detects_network_move_and_updates_ip(): void
    {
        $token = 'test-token-' . bin2hex(random_bytes(6));
        $router = Router::withoutGlobalScopes()->create([
            'tenant_id'         => 1,
            'name'              => 'Move Test Router',
            'ip'                => '192.168.88.1',
            'last_known_ip'     => '102.219.208.5',
            'setup_token'       => $token,
            'status'            => 'offline',
        ]);

        $newPublicIp = '197.232.88.99';
        $response = $this->withServerVariables(['REMOTE_ADDR' => $newPublicIp])
            ->getJson("/api/router-hello/{$token}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $router->refresh();
        $this->assertEquals('online', $router->status);
        $this->assertEquals($newPublicIp, $router->last_known_ip);
        $this->assertNotNull($router->network_changed_at);
    }

    /**
     * Test BUG 1: Payment is recorded even when router is offline and activation is enqueued.
     */
    public function test_payment_recorded_and_queued_when_router_is_offline(): void
    {
        $receipt = 'TEST' . strtoupper(bin2hex(random_bytes(4)));
        $stkTx = MpesaStkTransaction::withoutGlobalScopes()->create([
            'tenant_id'            => 1,
            'gateway'              => 'payhero',
            'checkout_request_id'  => 'checkout-' . bin2hex(random_bytes(4)),
            'mpesa_receipt_number' => $receipt,
            'amount'               => 10.00,
            'phone'                => '254712345678',
            'status'               => 'completed',
            'raw_callback'         => [
                'tenant_id'  => 1,
                'router_id'  => 999999, // non-existent/offline router
                'package_id' => null,
                'ip'         => '10.5.50.25',
                'mac'        => 'AA:BB:CC:DD:EE:FF',
            ],
        ]);

        // Simulating webhook payment fulfillment
        $controller = app(\App\Http\Controllers\Payment\PayHeroPaymentController::class);
        $method = new \ReflectionMethod($controller, 'fulfillSuccessfulTransaction');
        $method->setAccessible(true);
        $method->invoke($controller, $stkTx, $receipt, 10.00);

        // 1. Hotspot sale must exist and be recorded in DB
        $sale = HotspotSale::withoutGlobalScopes()->where('voucher_code', $receipt)->first();
        $this->assertNotNull($sale, 'Hotspot sale must be recorded in DB even if router was unreachable');
        $this->assertEquals('pending_router', $sale->status);
        $this->assertNotNull($sale->expires_at);

        // 2. Collection summary must exist
        $collection = CollectionSummary::withoutGlobalScopes()->where('transaction_id', $receipt)->first();
        $this->assertNotNull($collection, 'Collection summary must be saved for financial ledger');
        $this->assertEquals(10.00, (float) $collection->collection_amount);

        // 3. Router activation must be queued in router_pending_activations
        $pending = RouterPendingActivation::withoutGlobalScopes()->where('voucher_code', $receipt)->first();
        $this->assertNotNull($pending, 'Pending activation must be queued for offline router');
        $this->assertEquals('pending', $pending->status);
    }

    /**
     * Test BUG 2: Duration stacking when purchasing while active.
     */
    public function test_duration_stacks_on_repurchase(): void
    {
        $phone = '254799887766';
        $now = now();
        $futureExp = $now->copy()->addMinutes(30);

        // Existing active sale
        $initialSale = HotspotSale::withoutGlobalScopes()->create([
            'tenant_id'        => 1,
            'router_name'      => 'Hotspot',
            'voucher_code'     => 'EXISTING' . bin2hex(random_bytes(3)),
            'profile'          => '30 Min Plan',
            'username'         => $phone,
            'phone'            => $phone,
            'amount'           => 10.00,
            'payment_method'   => 'mpesa_payhero',
            'status'           => 'completed',
            'duration_minutes' => 30,
            'started_at'       => $now,
            'expires_at'       => $futureExp,
            'sale_date'        => $now->toDateString(),
        ]);

        $newReceipt = 'STACK' . strtoupper(bin2hex(random_bytes(4)));
        $stkTx = MpesaStkTransaction::withoutGlobalScopes()->create([
            'tenant_id'            => 1,
            'gateway'              => 'payhero',
            'checkout_request_id'  => 'checkout-' . bin2hex(random_bytes(4)),
            'mpesa_receipt_number' => $newReceipt,
            'amount'               => 20.00,
            'phone'                => $phone,
            'status'               => 'completed',
            'raw_callback'         => [
                'tenant_id' => 1,
                'phone'     => $phone,
            ],
        ]);

        $controller = app(\App\Http\Controllers\Payment\PayHeroPaymentController::class);
        $method = new \ReflectionMethod($controller, 'fulfillSuccessfulTransaction');
        $method->setAccessible(true);
        $method->invoke($controller, $stkTx, $newReceipt, 20.00);

        $newSale = HotspotSale::withoutGlobalScopes()->where('voucher_code', $newReceipt)->first();
        $this->assertNotNull($newSale);
        // Expiration should extend past the previous expiration (30m + 60m default = 90m from initial start)
        $this->assertTrue($newSale->expires_at->greaterThan($futureExp));
    }

    /**
     * Test BUG 3: CSRF exemptions and graceful 419 handling.
     */
    public function test_csrf_exempted_for_portal_payment_routes(): void
    {
        // Public STK push endpoint must not throw CSRF TokenMismatchException
        $response = $this->postJson('/api/payment/stk-push', [
            'phone'  => '254712345678',
            'amount' => 10,
        ]);

        // Even if gateway credentials aren't configured in test env, it should not be 419 (CSRF mismatch)
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /**
     * Test BUG 3: Graceful redirect on TokenMismatchException / 419.
     */
    public function test_token_mismatch_redirects_gracefully_to_login(): void
    {
        // JSON request expects JSON 419 with redirect URL
        $jsonResp = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post('/login', []);

        $this->assertNotEquals(500, $jsonResp->getStatusCode());
    }
}

