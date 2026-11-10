<?php

namespace Tests\Feature;

use App\Models\CollectionSummary;
use App\Models\MpesaStkTransaction;
use App\Models\UnmatchedPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarajaStkCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_daraja_callback_with_cancelled_user_does_not_activate_and_marks_status_cancelled(): void
    {
        $checkoutId = 'ws_CO_TEST_CANCEL_001';

        $stkTx = MpesaStkTransaction::create([
            'gateway'             => 'daraja',
            'merchant_request_id' => 'MR_TEST_001',
            'checkout_request_id' => $checkoutId,
            'amount'              => 1000.00,
            'phone'               => '254700000001',
            'reference'           => 'CUST-001',
            'status'              => 'pending',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_TEST_001',
                    'CheckoutRequestID' => $checkoutId,
                    'ResultCode'        => 1032,
                    'ResultDesc'        => 'Request cancelled by user',
                ],
            ],
        ];

        $response = $this->postJson('/api/payment/daraja/stk-callback', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);

        $stkTx->refresh();
        $this->assertEquals('cancelled', $stkTx->status);
        $this->assertEquals(1032, $stkTx->result_code);
        $this->assertEquals('Request cancelled by user', $stkTx->result_desc);

        // Ensure NO collection record was created
        $this->assertEquals(0, CollectionSummary::where('customer_id', 'CUST-001')->count());
    }

    public function test_daraja_callback_with_timeout_does_not_activate_and_marks_status_timeout(): void
    {
        $checkoutId = 'ws_CO_TEST_TIMEOUT_001';

        $stkTx = MpesaStkTransaction::create([
            'gateway'             => 'daraja',
            'merchant_request_id' => 'MR_TEST_002',
            'checkout_request_id' => $checkoutId,
            'amount'              => 1500.00,
            'phone'               => '254700000002',
            'reference'           => 'CUST-002',
            'status'              => 'pending',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_TEST_002',
                    'CheckoutRequestID' => $checkoutId,
                    'ResultCode'        => 1037,
                    'ResultDesc'        => 'DS timeout user cannot be reached',
                ],
            ],
        ];

        $response = $this->postJson('/api/payment/daraja/stk-callback', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);

        $stkTx->refresh();
        $this->assertEquals('timeout', $stkTx->status);
        $this->assertEquals(1037, $stkTx->result_code);

        // Ensure NO collection record was created
        $this->assertEquals(0, CollectionSummary::where('customer_id', 'CUST-002')->count());
    }

    public function test_daraja_callback_with_insufficient_funds_marks_status_failed(): void
    {
        $checkoutId = 'ws_CO_TEST_FAIL_001';

        $stkTx = MpesaStkTransaction::create([
            'gateway'             => 'daraja',
            'merchant_request_id' => 'MR_TEST_003',
            'checkout_request_id' => $checkoutId,
            'amount'              => 3000.00,
            'phone'               => '254700000003',
            'reference'           => 'CUST-003',
            'status'              => 'pending',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_TEST_003',
                    'CheckoutRequestID' => $checkoutId,
                    'ResultCode'        => 1,
                    'ResultDesc'        => 'The balance is insufficient for the requested transaction.',
                ],
            ],
        ];

        $response = $this->postJson('/api/payment/daraja/stk-callback', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);

        $stkTx->refresh();
        $this->assertEquals('failed', $stkTx->status);
        $this->assertEquals(1, $stkTx->result_code);

        // Ensure NO collection record was created
        $this->assertEquals(0, CollectionSummary::where('customer_id', 'CUST-003')->count());
    }

    public function test_daraja_callback_with_success_updates_status_and_logs_unmatched_if_customer_not_found(): void
    {
        $checkoutId = 'ws_CO_TEST_SUCCESS_001';
        $receipt = 'QK99DARAJA01';

        $stkTx = MpesaStkTransaction::create([
            'gateway'             => 'daraja',
            'merchant_request_id' => 'MR_TEST_004',
            'checkout_request_id' => $checkoutId,
            'amount'              => 1200.00,
            'phone'               => '254700000004',
            'reference'           => 'UNKNOWN-CUST-999',
            'status'              => 'pending',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR_TEST_004',
                    'CheckoutRequestID' => $checkoutId,
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'The service request is processed successfully.',
                    'CallbackMetadata'  => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 1200.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                            ['Name' => 'Balance'],
                            ['Name' => 'TransactionDate', 'Value' => 20261107234500],
                            ['Name' => 'PhoneNumber', 'Value' => 254700000004],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/payment/daraja/stk-callback', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);

        $stkTx->refresh();
        $this->assertEquals('completed', $stkTx->status);
        $this->assertEquals(0, $stkTx->result_code);
        $this->assertEquals($receipt, $stkTx->mpesa_receipt_number);

        // Verify unmatched payment was logged since UNKNOWN-CUST-999 does not exist
        $unmatched = UnmatchedPayment::where('transaction_code', $receipt)->first();
        $this->assertNotNull($unmatched);
        $this->assertEquals(1200.00, (float) $unmatched->amount);
        $this->assertEquals('daraja', $unmatched->source);
    }
}
