<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('routers')) {
            Schema::table('routers', function (Blueprint $table) {
                if (! Schema::hasColumn('routers', 'router_name')) {
                    $table->string('router_name')->nullable()->after('name');
                }
                if (! Schema::hasColumn('routers', 'ip_address')) {
                    $table->string('ip_address')->nullable()->after('ip');
                }
            });

            // Backfill existing values
            DB::statement("UPDATE routers SET router_name = name WHERE router_name IS NULL OR router_name = ''");
            DB::statement("UPDATE routers SET ip_address = COALESCE(vpn_ip, ip) WHERE ip_address IS NULL OR ip_address = ''");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('routers')) {
            Schema::table('routers', function (Blueprint $table) {
                if (Schema::hasColumn('routers', 'router_name')) {
                    $table->dropColumn('router_name');
                }
                if (Schema::hasColumn('routers', 'ip_address')) {
                    $table->dropColumn('ip_address');
                }
            });
        }
    }
};

