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
        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                if (!Schema::hasColumn('package_lists', 'includes_tv')) {
                    $table->boolean('includes_tv')->default(false)->after('type');
                }
                if (!Schema::hasColumn('package_lists', 'tv_channels')) {
                    $table->string('tv_channels')->nullable()->after('includes_tv');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                if (Schema::hasColumn('package_lists', 'tv_channels')) {
                    $table->dropColumn('tv_channels');
                }
                if (Schema::hasColumn('package_lists', 'includes_tv')) {
                    $table->dropColumn('includes_tv');
                }
            });
        }
    }
};
