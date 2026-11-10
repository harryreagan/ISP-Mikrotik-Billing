<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'ssh_port')) {
                $table->integer('ssh_port')->default(22)->after('api_port');
            }
            if (!Schema::hasColumn('routers', 'connection_type')) {
                $table->string('connection_type', 32)->default('wireguard')->after('type');
            }
            if (!Schema::hasColumn('routers', 'lan_interface')) {
                $table->string('lan_interface', 64)->default('ether2')->after('connection_type');
            }
            if (!Schema::hasColumn('routers', 'wan_interface')) {
                $table->string('wan_interface', 64)->default('ether1')->after('lan_interface');
            }
            if (!Schema::hasColumn('routers', 'hotspot_subnet')) {
                $table->string('hotspot_subnet', 64)->default('10.5.50.0/24')->after('wan_interface');
            }
            if (!Schema::hasColumn('routers', 'pppoe_pool')) {
                $table->string('pppoe_pool', 64)->default('10.0.0.0/24')->after('hotspot_subnet');
            }
            if (!Schema::hasColumn('routers', 'services')) {
                $table->json('services')->nullable()->after('pppoe_pool');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $cols = ['ssh_port', 'connection_type', 'lan_interface', 'wan_interface', 'hotspot_subnet', 'pppoe_pool', 'services'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('routers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

