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
        // 1. Add Wizard columns to routers table
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'setup_token')) {
                $table->string('setup_token', 64)->nullable()->index()->after('notes');
            }
            if (!Schema::hasColumn('routers', 'api_user')) {
                $table->string('api_user', 100)->nullable()->after('username');
            }
            if (!Schema::hasColumn('routers', 'api_password')) {
                $table->text('api_password')->nullable()->after('password');
            }
            if (!Schema::hasColumn('routers', 'wg_address')) {
                $table->string('wg_address', 45)->nullable()->after('vpn_ip');
            }
            if (!Schema::hasColumn('routers', 'port_roles')) {
                $table->json('port_roles')->nullable()->after('services');
            }
        });

        // Ensure status column can hold 'pending', 'online', 'ready'
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE routers MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {}

        // 2. Add template column to isp_portal_settings
        if (Schema::hasTable('isp_portal_settings')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('isp_portal_settings', 'template')) {
                    $table->string('template', 32)->default('midnight')->after('theme_layout');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $cols = ['setup_token', 'api_user', 'api_password', 'wg_address', 'port_roles'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('routers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('isp_portal_settings')) {
            Schema::table('isp_portal_settings', function (Blueprint $table) {
                if (Schema::hasColumn('isp_portal_settings', 'template')) {
                    $table->dropColumn('template');
                }
            });
        }
    }
};

