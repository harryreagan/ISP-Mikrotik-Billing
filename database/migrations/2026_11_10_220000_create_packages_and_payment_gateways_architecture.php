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
        // 1. Extend package_lists table
        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                if (!Schema::hasColumn('package_lists', 'type')) {
                    $table->string('type', 32)->default('hotspot')->after('package');
                }
                if (!Schema::hasColumn('package_lists', 'speed_download')) {
                    $table->unsignedInteger('speed_download')->nullable()->after('speed');
                }
                if (!Schema::hasColumn('package_lists', 'speed_download_unit')) {
                    $table->string('speed_download_unit', 10)->default('Mbps')->after('speed_download');
                }
                if (!Schema::hasColumn('package_lists', 'speed_upload')) {
                    $table->unsignedInteger('speed_upload')->nullable()->after('speed_download_unit');
                }
                if (!Schema::hasColumn('package_lists', 'speed_upload_unit')) {
                    $table->string('speed_upload_unit', 10)->default('Mbps')->after('speed_upload');
                }
                if (!Schema::hasColumn('package_lists', 'burst_speed')) {
                    $table->string('burst_speed', 64)->nullable()->after('speed_upload_unit');
                }
                if (!Schema::hasColumn('package_lists', 'validity_value')) {
                    $table->unsignedInteger('validity_value')->default(1)->after('price');
                }
                if (!Schema::hasColumn('package_lists', 'validity_unit')) {
                    $table->string('validity_unit', 20)->default('hours')->after('validity_value');
                }
                if (!Schema::hasColumn('package_lists', 'data_limit_mb')) {
                    $table->unsignedBigInteger('data_limit_mb')->nullable()->after('validity_unit');
                }
                if (!Schema::hasColumn('package_lists', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('show_on_site');
                }
                if (!Schema::hasColumn('package_lists', 'show_public')) {
                    $table->boolean('show_public')->default(true)->after('is_active');
                }
            });
        }

        // 2. Pivot table: package_router
        if (!Schema::hasTable('package_router')) {
            Schema::create('package_router', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_id')->index();
                $table->unsignedBigInteger('router_id')->index();
                $table->string('sync_status', 32)->default('pending'); // synced, pending, failed
                $table->text('sync_error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['package_id', 'router_id'], 'pkg_rtr_unique');
            });
        }

        // 3. Tenant-scoped Payment Channels table: isp_payment_channels
        if (!Schema::hasTable('isp_payment_channels')) {
            Schema::create('isp_payment_channels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('gateway', 32)->default('payhero'); // payhero, daraja, kopokopo
                $table->string('channel_type', 32)->default('till'); // till, paybill, bank
                $table->string('channel_name', 255);
                $table->unsignedBigInteger('payhero_channel_id')->nullable();
                $table->string('short_code', 64)->nullable(); // Till or Paybill
                $table->string('account_number', 64)->nullable(); // Paybill account or Bank account
                $table->string('bank_name', 128)->nullable();
                $table->string('bank_code', 64)->nullable();
                $table->text('api_key_encrypted')->nullable();
                $table->text('api_secret_encrypted')->nullable();
                $table->text('passkey_encrypted')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('status', 32)->default('active'); // active, pending, error
                $table->timestamp('last_tested_at')->nullable();
                $table->text('test_result')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_payment_channels');
        Schema::dropIfExists('package_router');
    }
};

