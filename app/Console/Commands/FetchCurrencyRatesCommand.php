<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchCurrencyRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'currency:update-rates';

    /**
     * The console command description.
     */
    protected $description = 'Refresh foreign exchange rates against KES from free exchange API daily';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching latest foreign exchange rates with base KES...');

        $rates = null;

        // Primary: open.er-api.com (free, high availability, no API key required)
        try {
            $response = Http::timeout(10)->get('https://open.er-api.com/v6/latest/KES');
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['rates']) && is_array($data['rates'])) {
                    $rates = $data['rates'];
                    $this->info('Successfully fetched rates from open.er-api.com');
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Primary API failed: ' . $e->getMessage());
        }

        // Secondary fallback: api.exchangerate-api.com
        if (!$rates) {
            try {
                $response = Http::timeout(10)->get('https://api.exchangerate-api.com/v4/latest/KES');
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['rates']) && is_array($data['rates'])) {
                        $rates = $data['rates'];
                        $this->info('Successfully fetched rates from fallback exchangerate-api.com');
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('Fallback API failed: ' . $e->getMessage());
            }
        }

        if (!$rates) {
            $this->error('Failed to retrieve live rates from both sources. Keeping existing database rates.');
            Log::warning('currency:update-rates failed to fetch live rates; using database fallback.');
            return self::FAILURE;
        }

        // Always keep KES = 1
        $rates['KES'] = 1.0;

        $dbCurrencies = CurrencyRate::all();
        $updatedCount = 0;

        foreach ($dbCurrencies as $currencyModel) {
            $code = strtoupper(trim($currencyModel->currency));
            if ($code === 'KES') {
                $currencyModel->update(['rate_from_kes' => 1.0]);
                continue;
            }

            if (isset($rates[$code]) && is_numeric($rates[$code]) && (float)$rates[$code] > 0) {
                $newRate = (float) $rates[$code];
                $currencyModel->update(['rate_from_kes' => $newRate]);
                $this->line("Updated {$code}: 1 KES = {$newRate} {$code}");
                $updatedCount++;
            }
        }

        // Refresh cache for landing page & billing queries
        Cache::forget('currency_rates_list');
        Cache::remember('currency_rates_list', now()->addDay(), function () {
            return CurrencyRate::ordered()->get()->keyBy('currency')->toArray();
        });

        $this->info("Completed! Updated {$updatedCount} exchange rates against KES.");
        return self::SUCCESS;
    }
}

