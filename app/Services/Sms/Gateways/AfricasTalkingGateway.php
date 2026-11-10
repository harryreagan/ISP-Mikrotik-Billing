<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use Codepagol\SmsBridge\Gateways\AbstractGateway;
use Codepagol\SmsBridge\Response\SmsResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AfricasTalkingGateway extends AbstractGateway
{
    /**
     * Normalize Kenyan phone number to E.164 (+2547XXXXXXXX or +2541XXXXXXXX).
     */
    protected function normalizePhoneNumber(string $number): string
    {
        $normalized = normalizeKenyanPhone($number);
        if ($normalized) {
            return '+' . $normalized;
        }

        // Fallback: clean digits and ensure leading +
        $digits = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        }
        return '+' . $digits;
    }

    /**
     * Send SMS via Africa's Talking API.
     */
    protected function processSend(): SmsResponse
    {
        $username = $this->credentials['username']
            ?: (siteUrlSettings('africastalking_username') ?: env('AFRICASTALKING_USERNAME', 'sandbox'));
        $apiKey = $this->credentials['api_key']
            ?: (siteUrlSettings('africastalking_api_key') ?: env('AFRICASTALKING_API_KEY', ''));
        $senderId = $this->credentials['sender_id']
            ?: (siteUrlSettings('africastalking_sender_id') ?: env('AFRICASTALKING_SENDER_ID', ''));

        if (empty($apiKey)) {
            Log::warning("AfricasTalkingGateway: API key is not configured.");
            return new SmsResponse(false, "Africa's Talking API key is missing.");
        }

        $isSandbox = strtolower(trim($username)) === 'sandbox';
        $endpoint = $isSandbox
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $recipientsString = implode(',', $this->recipients);

        $postData = [
            'username' => $username,
            'to'       => $recipientsString,
            'message'  => $this->messageBody,
        ];

        if (! empty($senderId)) {
            $postData['from'] = $senderId;
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->withHeaders([
                    'apiKey' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $postData);

            $data = $response->json();
            Log::info("AfricasTalking SMS Response: " . json_encode($data));

            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                $recipients = $data['SMSMessageData']['Recipients'];
                $firstRecipient = $recipients[0] ?? [];
                $status = strtolower($firstRecipient['status'] ?? '');

                if ($status === 'success' || str_contains($status, 'success')) {
                    return new SmsResponse(
                        true,
                        $data['SMSMessageData']['Message'] ?? 'SMS sent successfully via Africa\'s Talking',
                        $data
                    );
                }

                return new SmsResponse(
                    false,
                    'Africa\'s Talking error: ' . ($firstRecipient['status'] ?? 'Unknown error'),
                    $data
                );
            }

            return new SmsResponse(
                false,
                $data['errorMessage'] ?? 'Failed to send SMS via Africa\'s Talking',
                $data
            );
        } catch (\Exception $e) {
            Log::error("AfricasTalkingGateway exception: " . $e->getMessage());
            return new SmsResponse(false, 'Exception sending via Africa\'s Talking: ' . $e->getMessage());
        }
    }

    /**
     * Check Africa's Talking account balance.
     */
    public function balance()
    {
        $username = $this->credentials['username']
            ?: (siteUrlSettings('africastalking_username') ?: env('AFRICASTALKING_USERNAME', 'sandbox'));
        $apiKey = $this->credentials['api_key']
            ?: (siteUrlSettings('africastalking_api_key') ?: env('AFRICASTALKING_API_KEY', ''));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'API Key not configured',
                'data' => ['remaining_balance' => 'Not Configured']
            ];
        }

        $isSandbox = strtolower(trim($username)) === 'sandbox';
        $endpoint = $isSandbox
            ? "https://api.sandbox.africastalking.com/version1/user?username={$username}"
            : "https://api.africastalking.com/version1/user?username={$username}";

        try {
            $response = Http::timeout(10)
                ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
                ->get($endpoint);

            if ($response->successful()) {
                $rawBalance = $response->json('UserData.balance') ?? '0';
                return [
                    'status' => 'success',
                    'data' => [
                        'remaining_balance' => $rawBalance,
                        'expired_on' => null,
                    ]
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Failed to retrieve balance',
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
        $username = $this->credentials['username']
            ?: (siteUrlSettings('africastalking_username') ?: env('AFRICASTALKING_USERNAME', 'sandbox'));

        return [
            'status' => 'success',
            'data' => [
                'first_name' => 'Africa\'s',
                'last_name'  => 'Talking',
                'email'      => "User: {$username}",
            ]
        ];
    }
}
