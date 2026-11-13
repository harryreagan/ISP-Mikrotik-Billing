<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop the legacy foreign key constraint referencing router_lists
        if (Schema::hasTable('p_p_p_secrets')) {
            try {
                Schema::table('p_p_p_secrets', function (Blueprint $table) {
                    $table->dropForeign('p_p_p_secrets_router_name_foreign');
                });
            } catch (\Throwable $e) {
                // In case foreign key was already dropped or has a different name
            }

            if (!Schema::hasColumn('p_p_p_secrets', 'router_id')) {
                Schema::table('p_p_p_secrets', function (Blueprint $table) {
                    $table->unsignedBigInteger('router_id')->nullable()->after('router_name');
                });
            }
        }

        // 2. Sync all current routers from 'routers' into 'router_lists' for full backward compatibility
        try {
            if (Schema::hasTable('routers') && Schema::hasTable('router_lists')) {
                $routers = DB::table('routers')->get();
                foreach ($routers as $r) {
                    $rName = $r->name ?: ($r->router_name ?: 'Router-' . $r->id);
                    DB::table('router_lists')->updateOrInsert(
                        ['router_name' => $rName],
                        [
                            'tenant_id'  => $r->tenant_id,
                            'ip_address' => $r->vpn_ip ?: ($r->ip_address ?: ($r->ip ?: '10.10.0.1')),
                            'username'   => $r->api_user ?: ($r->username ?: 'admin'),
                            'password'   => $r->api_password ?: ($r->password ?: ''),
                            'api_port'   => $r->api_port ?: 8728,
                            'action'     => 'connected',
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};

