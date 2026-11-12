<?php

namespace App\Services\Sms;

use App\Models\IspSmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SmsBalanceService
{
    /**
     * Retrieve cached SMS balance status for the given tenant (cached 60 seconds).
     */
    public static function forTenant(?int $tenantId): array
    {
        if (! $tenantId) {
            return self::nonePayload();
        }

        return Cache::remember("sms_balance:{$tenantId}", 60, function () use ($tenantId) {
            return self::fetchForTenant($tenantId);
        });
    }

    /**
     * Clear the cached SMS balance for a tenant.
     */
    public static function forget(?int $tenantId): void
    {
        if ($tenantId) {
            Cache::forget("sms_balance:{$tenantId}");
        }
    }

    /**
     * Resolve active SMS gateway for the tenant and query its live balance.
     */
    protected static function fetchForTenant(int $tenantId): array
    {
        $gateway = IspSmsGateway::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if (! $gateway) {
            return self::nonePayload();
        }

        $type = strtolower(trim((string) $gateway->gateway_type));
        $providerLabel = self::providerLabel($type);

        try {
            $result = match ($type) {
                'africastalking' => self::fetchAfricasTalking($gateway),
                'twilio'         => self::fetchTwilio($gateway),
                'textsms'        => self::fetchTextSms($gateway),
                'advanta'        => self::fetchAdvanta($gateway),
                'system'         => self::fetchSystemWallet($gateway, $tenantId),
                default          => self::fetchFallbackOrUnsupported($gateway),
            };

            if (($result['unsupported'] ?? false) === true) {
                return [
                    'status'            => 'none',
                    'provider'          => $providerLabel,
                    'balance'           => null,
                    'balance_formatted' => null,
                    'unit'              => 'credits',
                    'updated_at'        => now()->toIso8601String(),
                ];
            }

            $amount = (float) ($result['amount'] ?? 0.0);
            $unit = (string) ($result['unit'] ?? 'credits');
            $threshold = (float) ($gateway->low_balance_threshold ?? 100.0);

            $status = 'ok';
            if ($amount <= 0) {
                $status = 'empty';
            } elseif ($amount < $threshold) {
                $status = 'low';
            }

            return [
                'status'            => $status,
                'provider'          => $providerLabel,
                'balance'           => round($amount, 2),
                'balance_formatted' => self::formatBalance($amount, $unit),
                'updated_at'        => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status'            => 'error',
                'provider'          => $providerLabel,
                'balance'           => null,
                'balance_formatted' => null,
                'updated_at'        => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Africa's Talking balance driver:
     * GET https://api.africastalking.com/version1/user?username={username}
     * (or sandbox host when username is 'sandbox')
     */
    protected static function fetchAfricasTalking(IspSmsGateway $gateway): array
    {
        $username = trim((string) ($gateway->api_username ?: 'sandbox'));
        $apiKey = $gateway->decryptedApiKey();

        if ($apiKey === '') {
            throw new RuntimeException("Africa's Talking API key is missing.");
        }

        $host = strtolower($username) === 'sandbox'
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';

        $response = Http::withHeaders([
            'apiKey' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(8)->get("{$host}/version1/user", [
            'username' => $username,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Africa's Talking balance check failed with HTTP {$response->status()}.");
        }

        $rawBalance = trim((string) data_get($response->json(), 'UserData.balance', ''));
        if ($rawBalance === '') {
            throw new RuntimeException("Africa's Talking response missing UserData.balance.");
        }

        // Parse strings like "KES 1234.5000" or "USD -5.00"
        if (preg_match('/^([A-Za-z]{3})\s*([+-]?\d+(?:,\d{3})*(?:\.\d+)?)$/', $rawBalance, $matches)) {
            $currency = strtoupper($matches[1]);
            $amount = (float) str_replace(',', '', $matches[2]);
            return ['amount' => $amount, 'unit' => $currency];
        }

        if (is_numeric($rawBalance)) {
            return ['amount' => (float) $rawBalance, 'unit' => 'KES'];
        }

        throw new RuntimeException("Unrecognized Africa's Talking balance format.");
    }

    /**
     * Twilio balance driver:
     * GET https://api.twilio.com/2010-04-01/Accounts/{sid}/Balance.json
     */
    protected static function fetchTwilio(IspSmsGateway $gateway): array
    {
        $sid = trim((string) $gateway->api_username);
        $token = $gateway->decryptedApiKey() ?: $gateway->decryptedApiSecret();

        if ($sid === '' || $token === '') {
            throw new RuntimeException('Twilio Account SID or Auth Token is missing.');
        }

        $response = Http::withBasicAuth($sid, $token)
            ->acceptJson()
            ->timeout(8)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Balance.json");

        if (! $response->successful()) {
            throw new RuntimeException("Twilio balance check failed with HTTP {$response->status()}.");
        }

        $data = $response->json();
        if (! is_array($data) || ! array_key_exists('balance', $data)) {
            throw new RuntimeException('Twilio response missing balance field.');
        }

        return [
            'amount' => (float) $data['balance'],
            'unit'   => strtoupper((string) ($data['currency'] ?? 'USD')),
        ];
    }

    /**
     * TextSMS Kenya balance driver.
     */
    protected static function fetchTextSms(IspSmsGateway $gateway): array
    {
        $apiKey = $gateway->decryptedApiKey();
        $partnerId = trim((string) $gateway->api_username);

        if ($apiKey === '' || $partnerId === '') {
            throw new RuntimeException('TextSMS credentials incomplete.');
        }

        $baseUrl = rtrim((string) ($gateway->endpoint_url ?: 'https://sms.textsms.co.ke'), '/');
        $url = str_contains($baseUrl, 'getbalance') ? $baseUrl : "{$baseUrl}/api/services/getbalance/";

        $response = Http::acceptJson()->timeout(8)->post($url, [
            'apikey'    => $apiKey,
            'partnerID' => $partnerId,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("TextSMS balance check failed with HTTP {$response->status()}.");
        }

        $data = $response->json() ?? [];
        if (! isset($data['credit']) && ! isset($data['balance'])) {
            throw new RuntimeException('TextSMS balance field missing.');
        }

        return [
            'amount' => (float) ($data['credit'] ?? $data['balance'] ?? 0),
            'unit'   => 'credits',
        ];
    }

    /**
     * Advanta SMS balance driver.
     */
    protected static function fetchAdvanta(IspSmsGateway $gateway): array
    {
        $apiKey = $gateway->decryptedApiKey();
        $partnerId = trim((string) $gateway->api_username);

        if ($apiKey === '' || $partnerId === '') {
            throw new RuntimeException('Advanta SMS credentials incomplete.');
        }

        $response = Http::acceptJson()->timeout(8)->post('https://quicksms.advantasms.com/api/services/getbalance/', [
            'apikey'    => $apiKey,
            'partnerID' => $partnerId,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Advanta balance check failed with HTTP {$response->status()}.");
        }

        $data = $response->json() ?? [];
        if (! isset($data['credit']) && ! isset($data['balance'])) {
            throw new RuntimeException('Advanta balance field missing.');
        }

        return [
            'amount' => (float) ($data['credit'] ?? $data['balance'] ?? 0),
            'unit'   => 'credits',
        ];
    }

    /**
     * Platform system wallet driver for tenants using the shared pool.
     */
    protected static function fetchSystemWallet(IspSmsGateway $gateway, int $tenantId): array
    {
        $wallet = DB::table('sms_wallets')->where('tenant_id', $tenantId)->first();
        if ($wallet && isset($wallet->balance)) {
            return ['amount' => (float) $wallet->balance, 'unit' => 'credits'];
        }

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if ($tenant && isset($tenant->sms_balance)) {
            return ['amount' => (float) $tenant->sms_balance, 'unit' => 'credits'];
        }

        if ($gateway->balance !== null) {
            return ['amount' => (float) $gateway->balance, 'unit' => 'credits'];
        }

        return ['amount' => 0.0, 'unit' => 'credits'];
    }

    /**
     * Graceful fallback for providers without a balance API.
     */
    protected static function fetchFallbackOrUnsupported(IspSmsGateway $gateway): array
    {
        if ($gateway->balance !== null) {
            return [
                'amount' => (float) $gateway->balance,
                'unit'   => 'credits',
            ];
        }

        return ['unsupported' => true, 'unit' => 'credits'];
    }

    protected static function formatBalance(float $amount, string $unit): string
    {
        if (strtolower($unit) === 'credits') {
            return number_format($amount, 0) . ' credits';
        }

        $decimals = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
        return strtoupper($unit) . ' ' . number_format($amount, $decimals);
    }

    protected static function providerLabel(string $type): string
    {
        return match ($type) {
            'africastalking' => "Africa's Talking",
            'twilio'         => 'Twilio',
            'textsms'        => 'TextSMS',
            'advanta'        => 'Advanta SMS',
            'blessed', 'blessedtexts' => 'Blessed Texts',
            'system'         => 'System Pool',
            default          => ucfirst($type ?: 'SMS'),
        };
    }

    protected static function nonePayload(): array
    {
        return [
            'status'            => 'none',
            'provider'          => null,
            'balance'           => null,
            'balance_formatted' => null,
            'updated_at'        => now()->toIso8601String(),
        ];
    }
}

