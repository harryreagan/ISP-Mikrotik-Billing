<?php

namespace Tests\Feature;

use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MainSiteData;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicatePaymentRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sms_forwarder.token' => 'secret-kenya-sms-token']);
        MainSiteData::setValue('sms_receiver_token', 'secret-kenya-sms-token');
    }

    public function test_sms_webhook_ignores_duplicate_transaction(): void
    {
        $trxId = 'QK99DUPL01';

        // Pre-insert transaction into collection summaries
        CollectionSummary::create([
            'customer_collection_unique_id' => 'CUST-TEST-001',
            'invoice_no'                    => 'INV-TEST-001',
            'collection_amount'             => 1500.00,
            'collection_date'               => now()->toDateString(),
            'transaction_id'                => $trxId,
            'payment_type'                  => 'mfs',
            'collected_by'                  => 'mpesa_sms',
        ]);

        $response = $this->withToken('secret-kenya-sms-token')
            ->postJson('/api/payment/mfs/sms-receiver', [
                'sender'  => 'MPESA',
                'message' => "{$trxId} Confirmed. Ksh 1,500.00 received from 0712345678 JOHN DOE.",
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'ignored',
            'message' => 'Transaction already processed',
        ]);

        // Ensure no duplicate record was added
        $this->assertEquals(1, CollectionSummary::where('transaction_id', $trxId)->count());
    }

    public function test_payment_service_fulfill_idempotency_prevents_duplicate_records(): void
    {
        $trxId = 'QK99DUPLICATE02';

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CUST-TEST-002',
            'invoice_no'                    => 'INV-TEST-002',
            'collection_amount'             => 2000.00,
            'collection_date'               => now()->toDateString(),
            'transaction_id'                => $trxId,
            'payment_type'                  => 'daraja',
            'collected_by'                  => 'daraja_stk',
        ]);

        $customer = new CustomersInfo([
            'customer_unique_id' => 'CUST-TEST-002',
            'mobile'             => '254712345678',
        ]);

        $service = app(PaymentService::class);
        $result = $service->fulfill($customer, 2000.00, 'daraja', $trxId);

        $this->assertTrue($result);
        $this->assertEquals(1, CollectionSummary::where('transaction_id', $trxId)->count());
    }
}
