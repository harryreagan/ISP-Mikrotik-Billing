<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prepaid_users')) {
            Schema::table('prepaid_users', function (Blueprint $table) {
                if (!Schema::hasColumn('prepaid_users', 'total_duration_seconds')) {
                    $table->unsignedInteger('total_duration_seconds')->nullable()->after('amount');
                }
                if (!Schema::hasColumn('prepaid_users', 'used_seconds')) {
                    $table->unsignedInteger('used_seconds')->default(0)->after('total_duration_seconds');
                }
                if (!Schema::hasColumn('prepaid_users', 'validity_expires_at')) {
                    $table->timestamp('validity_expires_at')->nullable()->after('expires_at');
                }
            });

            // Backfill total_duration_seconds from plan_name or hotspot_sales
            DB::statement("
                UPDATE prepaid_users 
                SET total_duration_seconds = 
                    CASE 
                        WHEN LOWER(plan_name) LIKE '%30 min%' THEN 1800
                        WHEN LOWER(plan_name) LIKE '%1 hour%' OR LOWER(plan_name) LIKE '%1hr%' THEN 3600
                        WHEN LOWER(plan_name) LIKE '%2 hour%' OR LOWER(plan_name) LIKE '%2hr%' THEN 7200
                        WHEN LOWER(plan_name) LIKE '%3 hour%' OR LOWER(plan_name) LIKE '%3hr%' THEN 10800
                        WHEN LOWER(plan_name) LIKE '%12 hour%' THEN 43200
                        WHEN LOWER(plan_name) LIKE '%24 hour%' OR LOWER(plan_name) LIKE '%1 day%' THEN 86400
                        WHEN LOWER(plan_name) LIKE '%7 day%' THEN 604800
                        WHEN LOWER(plan_name) LIKE '%30 day%' THEN 2592000
                        ELSE 3600
                    END
                WHERE total_duration_seconds IS NULL AND type = 'hotspot'
            ");

            // For existing rows where validity_expires_at is null, copy expires_at or add 30 days
            DB::statement("
                UPDATE prepaid_users 
                SET validity_expires_at = COALESCE(expires_at, DATE_ADD(created_at, INTERVAL 30 DAY))
                WHERE validity_expires_at IS NULL
            ");

            // Automatically restore hotspot users who were falsely expired while still having unused time
            DB::statement("
                UPDATE prepaid_users 
                SET status = 'offline', disconnection_reason = NULL 
                WHERE type = 'hotspot' 
                  AND status = 'expired' 
                  AND (used_seconds IS NULL OR used_seconds < total_duration_seconds)
                  AND (validity_expires_at IS NULL OR validity_expires_at > NOW())
            ");
        }

        if (Schema::hasTable('hotspot_sales')) {
            Schema::table('hotspot_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('hotspot_sales', 'duration_seconds')) {
                    $table->unsignedInteger('duration_seconds')->nullable()->after('duration_minutes');
                }
                if (!Schema::hasColumn('hotspot_sales', 'used_seconds')) {
                    $table->unsignedInteger('used_seconds')->default(0)->after('duration_seconds');
                }
                if (!Schema::hasColumn('hotspot_sales', 'validity_expires_at')) {
                    $table->timestamp('validity_expires_at')->nullable()->after('expires_at');
                }
            });

            DB::statement("
                UPDATE hotspot_sales 
                SET duration_seconds = duration_minutes * 60 
                WHERE duration_seconds IS NULL AND duration_minutes IS NOT NULL
            ");

            DB::statement("
                UPDATE hotspot_sales 
                SET validity_expires_at = expires_at 
                WHERE validity_expires_at IS NULL AND expires_at IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prepaid_users')) {
            Schema::table('prepaid_users', function (Blueprint $table) {
                if (Schema::hasColumn('prepaid_users', 'validity_expires_at')) {
                    $table->dropColumn('validity_expires_at');
                }
                if (Schema::hasColumn('prepaid_users', 'used_seconds')) {
                    $table->dropColumn('used_seconds');
                }
                if (Schema::hasColumn('prepaid_users', 'total_duration_seconds')) {
                    $table->dropColumn('total_duration_seconds');
                }
            });
        }

        if (Schema::hasTable('hotspot_sales')) {
            Schema::table('hotspot_sales', function (Blueprint $table) {
                if (Schema::hasColumn('hotspot_sales', 'validity_expires_at')) {
                    $table->dropColumn('validity_expires_at');
                }
                if (Schema::hasColumn('hotspot_sales', 'used_seconds')) {
                    $table->dropColumn('used_seconds');
                }
                if (Schema::hasColumn('hotspot_sales', 'duration_seconds')) {
                    $table->dropColumn('duration_seconds');
                }
            });
        }
    }
};
