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
        if (Schema::hasTable('isp_portal_settings')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('isp_portal_settings', 'badge_text')) {
                    $table->string('badge_text', 255)->nullable()->after('headline');
                }
                if (!Schema::hasColumn('isp_portal_settings', 'show_badge')) {
                    $table->boolean('show_badge')->default(true)->after('badge_text');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('isp_portal_settings')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                if (Schema::hasColumn('isp_portal_settings', 'show_badge')) {
                    $table->dropColumn('show_badge');
                }
                if (Schema::hasColumn('isp_portal_settings', 'badge_text')) {
                    $table->dropColumn('badge_text');
                }
            });
        }
    }
};

