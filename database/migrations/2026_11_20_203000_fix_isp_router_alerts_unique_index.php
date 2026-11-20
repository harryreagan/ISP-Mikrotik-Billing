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
        if (Schema::hasTable('isp_router_alerts')) {
            Schema::table('isp_router_alerts', function (Blueprint $table) {
                // Drop the overly restrictive unique index that prevents multiple resolved alerts
                try {
                    $table->dropUnique('uniq_router_alert');
                } catch (\Throwable $e) {}

                // Add standard composite index for fast lookups
                try {
                    $table->index(['router_id', 'alert_type', 'is_resolved'], 'idx_router_alert_lookup');
                } catch (\Throwable $e) {}
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('isp_router_alerts')) {
            Schema::table('isp_router_alerts', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_router_alert_lookup');
                } catch (\Throwable $e) {}
            });
        }
    }
};

