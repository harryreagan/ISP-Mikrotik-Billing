<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('main_site_data')) {
            // Update any existing BDT currencies to KES
            DB::table('main_site_data')
                ->where('type', 'site_currency')
                ->where(function ($q) {
                    $q->where('value', 'BDT')
                      ->orWhere('value', '৳')
                      ->orWhereNull('value')
                      ->orWhere('value', '');
                })
                ->update(['value' => 'KES']);

            // If no site_currency exists at all, insert it
            if (!DB::table('main_site_data')->where('type', 'site_currency')->exists()) {
                DB::table('main_site_data')->insert([
                    'type'       => 'site_currency',
                    'value'      => 'KES',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Also update currency_symbol
            DB::table('main_site_data')
                ->where('type', 'currency_symbol')
                ->where(function ($q) {
                    $q->where('value', 'BDT')
                      ->orWhere('value', '৳')
                      ->orWhereNull('value')
                      ->orWhere('value', '');
                })
                ->update(['value' => 'KES']);

            try {
                Cache::flush();
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};

