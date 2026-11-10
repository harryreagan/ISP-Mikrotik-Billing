<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('hotspot_vouchers', 'validity_type')) {
                $table->string('validity_type', 20)->default('uptime')->after('comment');
            }
            if (! Schema::hasColumn('hotspot_vouchers', 'validity_duration')) {
                $table->string('validity_duration', 20)->nullable()->after('validity_type');
            }
            if (! Schema::hasColumn('hotspot_vouchers', 'first_login_at')) {
                $table->timestamp('first_login_at')->nullable()->after('validity_duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_vouchers', function (Blueprint $table) {
            $table->dropColumn(['validity_type', 'validity_duration', 'first_login_at']);
        });
    }
};
