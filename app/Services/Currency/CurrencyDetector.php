<?php

namespace App\Services\Currency;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CurrencyDetector
{
    /**
     * Determine preferred display currency.
     * Priority:
     * 1. Existing cookie choice ('hsmart_currency')
     * 2. Cloudflare CF-IPCountry header
     * 3. Browser Accept-Language header
     * 4. Strict default fallback: KES
     */
    public static function detect(Request $request): string
    {
        // 1. User's explicitly chosen currency from cookie
        $cookieChoice = $request->cookie('hsmart_currency') ?: $request->cookie('hsmart_landing_currency');
        if ($cookieChoice) {
            $candidate = strtoupper(trim((string)$cookieChoice));
            if (self::hasRateFor($candidate)) {
                return $candidate;
            }
        }

        // 2. Cloudflare CF-IPCountry header
        $cfCountry = strtoupper(trim((string)($request->header('CF-IPCountry') ?? ($request->server('HTTP_CF_IPCOUNTRY') ?? ''))));
        if (!empty($cfCountry) && $cfCountry !== 'XX' && $cfCountry !== 'T1') {
            $currFromCountry = self::mapCountryToCurrency($cfCountry);
            if ($currFromCountry && self::hasRateFor($currFromCountry)) {
                return $currFromCountry;
            }
        }

        // 3. Accept-Language header (e.g. en-KE, en-UG, sw-TZ, rw-RW, en-US)
        $acceptLang = $request->header('Accept-Language', '');
        if (!empty($acceptLang)) {
            $detectedCountry = self::extractCountryFromAcceptLanguage($acceptLang);
            if ($detectedCountry) {
                $currFromLang = self::mapCountryToCurrency($detectedCountry);
                if ($currFromLang && self::hasRateFor($currFromLang)) {
                    return $currFromLang;
                }
            }
        }

        // 4. Default fallback: KES
        return 'KES';
    }

    /**
     * Map ISO 2-letter country code to default currency code.
     */
    public static function mapCountryToCurrency(string $countryCode): ?string
    {
        $map = [
            'KE' => 'KES',
            'UG' => 'UGX',
            'TZ' => 'TZS',
            'RW' => 'RWF',
            'US' => 'USD',
            'CA' => 'USD',
            'GB' => 'GBP',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'NL' => 'EUR',
        ];

        return $map[strtoupper($countryCode)] ?? null;
    }

    /**
     * Extract primary country from Accept-Language header if available.
     */
    protected static function extractCountryFromAcceptLanguage(string $header): ?string
    {
        // Matches language tags like en-KE, sw-TZ, en-US, fr-FR
        if (preg_match('/[a-z]{2}-([A-Z]{2})/i', $header, $matches)) {
            return strtoupper($matches[1]);
        }
        return null;
    }

    /**
     * Verify that we actually have a valid exchange rate for this currency in database / cache.
     */
    public static function hasRateFor(string $currency): bool
    {
        $rates = self::getRates();
        return isset($rates[strtoupper($currency)]);
    }

    /**
     * Retrieve all rates with KES guaranteed first.
     */
    public static function getRates(): array
    {
        return Cache::remember('currency_rates_list', now()->addHours(12), function () {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('currency_rates')) {
                    $dbRates = CurrencyRate::ordered()->get()->keyBy('currency')->toArray();
                    if (!empty($dbRates)) {
                        return $dbRates;
                    }
                }
            } catch (\Throwable $e) {}

            // Sensible baseline if table hasn't migrated yet
            return [
                'KES' => ['currency' => 'KES', 'rate_from_kes' => 1.0, 'symbol' => 'KES', 'name' => 'Kenyan Shilling'],
                'USD' => ['currency' => 'USD', 'rate_from_kes' => 0.00775, 'symbol' => '$', 'name' => 'US Dollar'],
                'UGX' => ['currency' => 'UGX', 'rate_from_kes' => 28.5, 'symbol' => 'USh', 'name' => 'Ugandan Shilling'],
                'TZS' => ['currency' => 'TZS', 'rate_from_kes' => 20.2, 'symbol' => 'TSh', 'name' => 'Tanzanian Shilling'],
                'RWF' => ['currency' => 'RWF', 'rate_from_kes' => 10.6, 'symbol' => 'RF', 'name' => 'Rwandan Franc'],
                'EUR' => ['currency' => 'EUR', 'rate_from_kes' => 0.0071, 'symbol' => '€', 'name' => 'Euro'],
                'GBP' => ['currency' => 'GBP', 'rate_from_kes' => 0.0061, 'symbol' => '£', 'name' => 'British Pound'],
            ];
        });
    }
}
