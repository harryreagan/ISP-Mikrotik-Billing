<?php

use Codepagol\SmsBridge\Models\SmsGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sms_gateways')) {
            // 1. Africa's Talking
            SmsGateway::firstOrCreate(
                ['name' => 'africastalking'],
                [
                    'api_url'         => 'https://api.africastalking.com/version1/messaging',
                    'api_send_url'    => 'https://api.africastalking.com/version1/messaging',
                    'api_balance_url' => 'https://api.africastalking.com/version1/user',
                    'username'        => env('AFRICASTALKING_USERNAME', 'sandbox'),
                    'api_key'         => env('AFRICASTALKING_API_KEY', ''),
                    'sender_id'       => env('AFRICASTALKING_SENDER_ID', ''),
                    'priority'        => 1,
                    'is_active'       => true,
                    'is_default'      => false,
                ]
            );

            // 2. TextSMS Kenya
            SmsGateway::firstOrCreate(
                ['name' => 'textsms'],
                [
                    'api_url'         => 'https://sms.textsms.co.ke/api/services/sendsms/',
                    'api_send_url'    => 'https://sms.textsms.co.ke/api/services/sendsms/',
                    'api_balance_url' => 'https://sms.textsms.co.ke/api/services/getbalance/',
                    'username'        => env('TEXTSMS_PARTNER_ID', ''),
                    'api_key'         => env('TEXTSMS_API_KEY', ''),
                    'sender_id'       => env('TEXTSMS_SHORTCODE', ''),
                    'priority'        => 2,
                    'is_active'       => true,
                    'is_default'      => true, // Default to TextSMS for Kenya
                ]
            );

            // Unset log as default if textsms is present
            SmsGateway::where('name', 'log')->update(['is_default' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sms_gateways')) {
            SmsGateway::whereIn('name', ['africastalking', 'textsms'])->delete();
            SmsGateway::where('name', 'log')->update(['is_default' => true]);
        }
    }
};

