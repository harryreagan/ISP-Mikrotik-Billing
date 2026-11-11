<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hotspot_vouchers')) {
            Schema::table('hotspot_vouchers', function (Blueprint $table) {
                if (!Schema::hasColumn('hotspot_vouchers', 'router_name')) {
                    $table->string('router_name', 100)->nullable()->index()->after('router_id');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'profile')) {
                    $table->string('profile', 100)->nullable()->after('code');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'batch_name')) {
                    $table->string('batch_name', 100)->nullable()->index()->after('voucher_batch_id');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'price')) {
                    $table->decimal('price', 10, 2)->default(0)->after('pin');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'used_by')) {
                    $table->string('used_by', 100)->nullable()->after('status');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'mac_address')) {
                    $table->string('mac_address', 64)->nullable()->after('used_by');
                }
                if (!Schema::hasColumn('hotspot_vouchers', 'comment')) {
                    $table->text('comment')->nullable()->after('mac_address');
                }
            });
        }
    }

    public function down(): void
    {
    }
};

