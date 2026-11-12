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
        if (Schema::hasTable('isp_sms_gateways')) {
            Schema::table('isp_sms_gateways', function (Blueprint $table) {
                if (! Schema::hasColumn('isp_sms_gateways', 'low_balance_threshold')) {
                    $table->decimal('low_balance_threshold', 12, 2)->default(100.00)->after('balance');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('isp_sms_gateways') && Schema::hasColumn('isp_sms_gateways', 'low_balance_threshold')) {
            Schema::table('isp_sms_gateways', function (Blueprint $table) {
                $table->dropColumn('low_balance_threshold');
            });
        }
    }
};

