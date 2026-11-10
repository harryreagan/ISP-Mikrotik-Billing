<?php

namespace Tests\Feature;

use App\Models\MainSiteData;
use App\Models\UnmatchedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaSmsWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sms_forwarder.token' => 'secret-kenya-sms-token']);
        MainSiteData::setValue('sms_receiver_token', 'secret-kenya-sms-token');
    }

    public function test_rejects_query_string_token_authentication(): void
    {
        $response = $this->postJson('/api/payment/mfs/sms-receiver?api_token=secret-kenya-sms-token', [
            'sender' => 'MPESA',
            'message' => 'QK71TEST01 Confirmed. Ksh 1,000.00 received from 0712345678.',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Query string token authentication is disabled. Provide Bearer token in Authorization header.',
        ]);
    }

    public function test_rejects_missing_bearer_token_header(): void
    {
        $response = $this->postJson('/api/payment/mfs/sms-receiver', [
            'sender' => 'MPESA',
            'message' => 'QK71TEST02 Confirmed. Ksh 1,000.00 received from 0712345678.',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Authorization header with Bearer token is required.',
        ]);
    }

    public function test_rejects_invalid_bearer_token(): void
    {
        $response = $this->withToken('wrong-bearer-token')
            ->postJson('/api/payment/mfs/sms-receiver', [
                'sender' => 'MPESA',
                'message' => 'QK71TEST03 Confirmed. Ksh 1,000.00 received from 0712345678.',
            ]);

        $response->assertStatus(401);
    }

    public function test_rejects_non_mpesa_sender(): void
    {
        $response = $this->withToken('secret-kenya-sms-token')
            ->postJson('/api/payment/mfs/sms-receiver', [
                'sender' => 'AIRTEL',
                'message' => 'QK71TEST04 Confirmed. Ksh 1,000.00 received from 0712345678.',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Sender must be MPESA',
        ]);
    }

    public function test_rejects_sms_with_sensitive_otp_pin_keywords(): void
    {
        $response = $this->withToken('secret-kenya-sms-token')
            ->postJson('/api/payment/mfs/sms-receiver', [
                'sender' => 'MPESA',
                'message' => 'Your one-time pin OTP is 4321 for secret code.',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'ignored',
            'message' => 'Security keyword detected in SMS content',
        ]);
    }

    public function test_logs_valid_unmatched_sms_to_unmatched_payments_table(): void
    {
        $trxCode = 'QK71UNM' . rand(100, 999);
        $response = $this->withToken('secret-kenya-sms-token')
            ->postJson('/api/payment/mfs/sms-receiver', [
                'sender' => 'MPESA',
                'message' => "{$trxCode} Confirmed. Ksh 1,800.00 received from 0722112233 PETER on 7/11/26 for account non_existent_user.",
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'unmatched',
            'transaction_code' => $trxCode,
        ]);

        $this->assertDatabaseHas('unmatched_payments', [
            'transaction_code' => $trxCode,
            'status' => 'pending',
            'amount' => 1800.00,
        ]);
    }
}
