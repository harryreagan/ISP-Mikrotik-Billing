<?php

namespace Tests\Unit;

use App\Services\MpesaSmsParser;
use PHPUnit\Framework\TestCase;

class MpesaSmsParserTest extends TestCase
{
    public function test_parses_paybill_with_account_number(): void
    {
        $message = "QK71ABC123 Confirmed. Ksh1,500.00 sent to HSMART ISP for account user001 on 7/11/26 at 8:30 PM. New M-PESA balance is Ksh500.00.";
        $parsed = MpesaSmsParser::parse($message);

        $this->assertNotNull($parsed);
        $this->assertEquals('QK71ABC123', $parsed['transaction_code']);
        $this->assertEquals(1500.00, $parsed['amount']);
        $this->assertEquals('user001', $parsed['account_reference']);
    }

    public function test_parses_till_buy_goods_payment(): void
    {
        $message = "RK92XYZ456 Confirmed. Ksh 2,000.00 paid to HSMART NETWORKS on 7/11/26 at 9:00 PM. New M-PESA balance is Ksh100.00.";
        $parsed = MpesaSmsParser::parse($message);

        $this->assertNotNull($parsed);
        $this->assertEquals('RK92XYZ456', $parsed['transaction_code']);
        $this->assertEquals(2000.00, $parsed['amount']);
    }

    public function test_parses_send_money_received_with_phone_and_name(): void
    {
        $message = "SK83KLM789 Confirmed. You have received Ksh 3,500.00 from JOHN DOE 0712345678 on 7/11/26 at 9:15 PM. New M-PESA balance is Ksh4,000.00.";
        $parsed = MpesaSmsParser::parse($message);

        $this->assertNotNull($parsed);
        $this->assertEquals('SK83KLM789', $parsed['transaction_code']);
        $this->assertEquals(3500.00, $parsed['amount']);
        $this->assertEquals('254712345678', $parsed['phone']);
        $this->assertEquals('JOHN DOE', $parsed['sender_name']);
    }

    public function test_parses_paybill_with_phone_name_and_account_reference(): void
    {
        $message = "QK71ABC999 Confirmed. Ksh 2,500.00 received from 0722000000 JANE DOE on 7/11/26 at 10:00 AM for account acc_alice.";
        $parsed = MpesaSmsParser::parse($message);

        $this->assertNotNull($parsed);
        $this->assertEquals('QK71ABC999', $parsed['transaction_code']);
        $this->assertEquals(2500.00, $parsed['amount']);
        $this->assertEquals('254722000000', $parsed['phone']);
        $this->assertEquals('JANE DOE', $parsed['sender_name']);
        $this->assertEquals('acc_alice', $parsed['account_reference']);
    }

    public function test_rejects_empty_or_non_mpesa_text(): void
    {
        $this->assertNull(MpesaSmsParser::parse(''));
        $this->assertNull(MpesaSmsParser::parse('Hello, your meeting is scheduled for tomorrow at 10 AM.'));
        $this->assertNull(MpesaSmsParser::parse('Your bank balance is USD 100.'));
    }
}

