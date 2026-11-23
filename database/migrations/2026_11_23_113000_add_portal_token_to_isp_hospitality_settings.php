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
        if (Schema::hasTable('isp_hospitality_settings')) {
            Schema::table('isp_hospitality_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('isp_hospitality_settings', 'portal_token')) {
                    $table->string('portal_token', 64)->nullable()->unique()->after('api_key');
                }
                if (!Schema::hasColumn('isp_hospitality_settings', 'business_name')) {
                    $table->string('business_name', 128)->nullable()->after('tenant_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('isp_hospitality_settings')) {
            Schema::table('isp_hospitality_settings', function (Blueprint $table) {
                if (Schema::hasColumn('isp_hospitality_settings', 'portal_token')) {
                    $table->dropColumn('portal_token');
                }
                if (Schema::hasColumn('isp_hospitality_settings', 'business_name')) {
                    $table->dropColumn('business_name');
                }
            });
        }
    }
};

