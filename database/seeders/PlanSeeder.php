<?php

namespace Database\Seeders;

use App\Models\CurrencyRate;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed initial platform subscription plans and currency exchange rates.
     */
    public function run(): void
    {
        // 1. Subscription Plans (Base price strictly in KES)
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'base_price_kes' => 400,
                'router_limit' => 2,
                'customer_limit' => 150,
                'features' => [
                    '2 routers',
                    '150 customers',
                    'Mobile money, cards & bank',
                    'SMS, WhatsApp & email alerts',
                    'Unlimited admin users',
                ],
                'is_popular' => false,
                'badge' => null,
                'button_text' => 'Choose Starter',
                'button_url' => 'https://isp.hsmart.co.ke/register?plan=starter',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'base_price_kes' => 1000,
                'router_limit' => 5,
                'customer_limit' => 500,
                'features' => [
                    '5 routers',
                    '500 customers',
                    'Resellers and vouchers',
                    'SMS, WhatsApp & email alerts',
                    'Unlimited admin users',
                ],
                'is_popular' => true,
                'badge' => 'POPULAR',
                'button_text' => 'Choose Business',
                'button_url' => 'https://isp.hsmart.co.ke/register?plan=business',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'base_price_kes' => null, // Custom
                'router_limit' => null,
                'customer_limit' => null,
                'features' => [
                    'Unlimited routers',
                    'Unlimited customers',
                    'Dedicated server / VPS',
                    'Custom captive portal branding',
                    '24/7 Priority SLA support',
                ],
                'is_popular' => false,
                'badge' => null,
                'button_text' => 'Talk to us',
                'button_url' => 'https://wa.me/254743606108?text=Hello%20HSmart%20Team%2C%20I%20am%20interested%20in%20the%20Enterprise%20ISP%20Plan',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }

        // 2. Initial Base Currency Rates (Base: 1 KES)
        $currencies = [
            [
                'currency' => 'KES',
                'rate_from_kes' => 1.00000000,
                'symbol' => 'KES',
                'name' => 'Kenyan Shilling',
                'country_codes' => ['KE'],
                'sort_order' => 0,
            ],
            [
                'currency' => 'USD',
                'rate_from_kes' => 0.00775000,
                'symbol' => '$',
                'name' => 'US Dollar',
                'country_codes' => ['US', 'CA'],
                'sort_order' => 1,
            ],
            [
                'currency' => 'UGX',
                'rate_from_kes' => 28.50000000,
                'symbol' => 'USh',
                'name' => 'Ugandan Shilling',
                'country_codes' => ['UG'],
                'sort_order' => 2,
            ],
            [
                'currency' => 'TZS',
                'rate_from_kes' => 20.20000000,
                'symbol' => 'TSh',
                'name' => 'Tanzanian Shilling',
                'country_codes' => ['TZ'],
                'sort_order' => 3,
            ],
            [
                'currency' => 'RWF',
                'rate_from_kes' => 10.60000000,
                'symbol' => 'RF',
                'name' => 'Rwandan Franc',
                'country_codes' => ['RW'],
                'sort_order' => 4,
            ],
            [
                'currency' => 'EUR',
                'rate_from_kes' => 0.00710000,
                'symbol' => '€',
                'name' => 'Euro',
                'country_codes' => ['DE', 'FR', 'IT', 'ES', 'NL'],
                'sort_order' => 5,
            ],
            [
                'currency' => 'GBP',
                'rate_from_kes' => 0.00610000,
                'symbol' => '£',
                'name' => 'British Pound',
                'country_codes' => ['GB'],
                'sort_order' => 6,
            ],
        ];

        foreach ($currencies as $c) {
            CurrencyRate::updateOrCreate(
                ['currency' => $c['currency']],
                $c
            );
        }
    }
}
