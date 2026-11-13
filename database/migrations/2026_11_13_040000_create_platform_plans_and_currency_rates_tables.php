<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('platform_plans')) {
            Schema::create('platform_plans', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->unsignedInteger('base_price_kes')->nullable()->comment('Base subscription price in Kenyan Shillings. Null for Custom/Enterprise.');
                $table->unsignedInteger('router_limit')->nullable();
                $table->unsignedInteger('customer_limit')->nullable();
                $table->json('features')->nullable();
                $table->boolean('is_popular')->default(false);
                $table->string('badge')->nullable();
                $table->string('button_text')->default('Choose Plan');
                $table->string('button_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('currency_rates')) {
            Schema::create('currency_rates', function (Blueprint $table) {
                $table->id();
                $table->string('currency', 3)->unique()->comment('3-letter ISO code e.g. KES, USD, UGX, TZS');
                $table->decimal('rate_from_kes', 16, 8)->default(1.00000000)->comment('Conversion multiplier: amount_in_kes * rate_from_kes');
                $table->string('symbol', 10)->default('KES');
                $table->string('name', 60)->default('Kenyan Shilling');
                $table->json('country_codes')->nullable()->comment('ISO country codes mapped to this currency e.g. ["KE"]');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
        Schema::dropIfExists('currency_rates');
    }
};

