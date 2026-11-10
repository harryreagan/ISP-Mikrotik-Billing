<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use Codepagol\SmsBridge\Gateways\AbstractGateway;
use Codepagol\SmsBridge\Response\SmsResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TextSmsGateway extends AbstractGateway
{
    /**
     * Normalize Kenyan phone number to 2547XXXXXXXX or 2541XXXXXXXX format.
     */
    protected function normalizePhoneNumber(string $number): string
    {
        $normalized = normalizeKenyanPhone($number);
        if ($normalized) {
            return $normalized;
        }

        $digits = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * Send SMS via TextSMS Kenya API.
     */
    protected function processSend(): SmsResponse
    {
        $apiKey = $this->credentials['api_key']
            ?: (siteUrlSettings('textsms_api_key') ?: env('TEXTSMS_API_KEY', ''));
        $partnerId = $this->credentials['username']
            ?: (siteUrlSettings('textsms_partner_id') ?: env('TEXTSMS_PARTNER_ID', ''));
        $shortcode = $this->credentials['sender_id']
            ?: (siteUrlSettings('textsms_shortcode') ?: env('TEXTSMS_SHORTCODE', ''));
        $baseUrl = rtrim(siteUrlSettings('textsms_base_url') ?: env('TEXTSMS_BASE_URL', 'https://sms.textsms.co.ke'), '/');

        if (empty($apiKey) || empty($partnerId)) {
            Log::warning("TextSmsGateway: Missing API Key or Partner ID.");
            return new SmsResponse(false, 'TextSMS API Key or Partner ID is not configured.');
        }

        $endpoint = "{$baseUrl}/api/services/sendsms/";

        $recipientsString = implode(',', $this->recipients);

        $payload = [
            'apikey'    => $apiKey,
            'partnerID' => $partnerId,
            'message'   => $this->messageBody,
            'shortcode' => $shortcode,
            'mobile'    => $recipientsString,
        ];

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload);

            $data = $response->json();
            Log::info("TextSMS Response: " . json_encode($data));

            if ($response->successful() && isset($data['responses'])) {
                $responses = $data['responses'];
                $first = $responses[0] ?? [];
                $code = (int) ($first['respose-code'] ?? $first['response-code'] ?? 0);
                $desc = $first['response-description'] ?? 'OK';

                if ($code === 200) {
                    return new SmsResponse(true, "TextSMS delivered successfully: {$desc}", $data);
                }

                return new SmsResponse(false, "TextSMS error [{$code}]: {$desc}", $data);
            }

            return new SmsResponse(false, $data['message'] ?? 'Failed to send SMS via TextSMS', $data);
        } catch (\Exception $e) {
            Log::error("TextSmsGateway exception: " . $e->getMessage());
            return new SmsResponse(false, 'Exception sending via TextSMS: ' . $e->getMessage());
        }
    }

    /**
     * Check TextSMS account balance.
     */
    public function balance()
    {
        $apiKey = $this->credentials['api_key']
            ?: (siteUrlSettings('textsms_api_key') ?: env('TEXTSMS_API_KEY', ''));
        $partnerId = $this->credentials['username']
            ?: (siteUrlSettings('textsms_partner_id') ?: env('TEXTSMS_PARTNER_ID', ''));
        $baseUrl = rtrim(siteUrlSettings('textsms_base_url') ?: env('TEXTSMS_BASE_URL', 'https://sms.textsms.co.ke'), '/');

        if (empty($apiKey) || empty($partnerId)) {
            return [
                'status' => 'error',
                'message' => 'Credentials not set',
                'data' => ['remaining_balance' => 'Not Configured']
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
                ->post("{$baseUrl}/api/services/getbalance/", [
                    'apikey'    => $apiKey,
                    'partnerID' => $partnerId,
                ]);

            if ($response->successful()) {
                $credit = $response->json('credit') ?? $response->json('balance') ?? '0';
                return [
                    'status' => 'success',
                    'data' => [
                        'remaining_balance' => $credit,
                        'expired_on' => null,
                    ]
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Failed to check balance',
                'data' => ['remaining_balance' => 'Error']
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => ['remaining_balance' => 'Offline']
            ];
        }
    }

    public function profile()
    {
        $partnerId = $this->credentials['username']
            ?: (siteUrlSettings('textsms_partner_id') ?: env('TEXTSMS_PARTNER_ID', ''));
        $shortcode = $this->credentials['sender_id']
            ?: (siteUrlSettings('textsms_shortcode') ?: env('TEXTSMS_SHORTCODE', ''));

        return [
            'status' => 'success',
            'data' => [
                'first_name' => 'TextSMS',
                'last_name'  => 'Kenya',
                'email'      => "Partner: {$partnerId} | SC: {$shortcode}",
            ]
        ];
    }
}
