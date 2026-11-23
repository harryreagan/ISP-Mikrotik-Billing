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
        // 1. Hospitality & POS Settings per Tenant
        if (!Schema::hasTable('isp_hospitality_settings')) {
            Schema::create('isp_hospitality_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('api_key', 64)->unique()->index();
                $table->boolean('is_enabled')->default(true);
                
                // Hotel Rules
                $table->unsignedBigInteger('default_hotel_package_id')->nullable();
                $table->integer('hotel_duration_hours')->default(24);
                $table->integer('hotel_device_limit')->default(3);
                $table->boolean('allow_room_guest_login')->default(true);

                // Restaurant & POS Rules
                $table->unsignedBigInteger('default_pos_package_id')->nullable();
                $table->decimal('pos_min_spend', 10, 2)->default(100.00);
                $table->integer('pos_duration_hours')->default(2);
                $table->integer('pos_device_limit')->default(1);

                // Thermal Receipt Custom Text
                $table->string('receipt_header')->default('COMPLIMENTARY GUEST WI-FI');
                $table->string('receipt_footer')->default('Scan QR code with phone camera to connect');
                $table->unsignedBigInteger('selected_router_id')->nullable();
                $table->string('webhook_secret', 64)->nullable();

                $table->timestamps();
            });
        }

        // 2. Hospitality Issued Tickets & Folio Receipts Log
        if (!Schema::hasTable('isp_hospitality_tickets')) {
            Schema::create('isp_hospitality_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('ticket_code', 32)->index();
                $table->string('ticket_type', 32)->default('pos_receipt')->index(); // 'pos_receipt', 'hotel_guest'
                $table->string('source_system', 64)->default('hsmart_pos')->index(); // 'hsmart_pos', 'opera_pms', 'cloudbeds', 'loyverse', etc.
                
                // Guest & Receipt Context
                $table->string('guest_name')->nullable();
                $table->string('guest_phone', 32)->nullable();
                $table->string('room_number', 32)->nullable()->index();
                $table->string('receipt_ref', 64)->nullable()->index();
                $table->decimal('bill_amount', 10, 2)->nullable();

                // Quota & Network
                $table->integer('duration_hours')->default(2);
                $table->integer('device_limit')->default(1);
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('router_id')->nullable();

                // Status & Tracking
                $table->string('status', 24)->default('active')->index(); // 'active', 'used', 'revoked', 'expired'
                $table->timestamp('first_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('qr_url')->nullable();
                $table->string('bound_mac', 64)->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_hospitality_tickets');
        Schema::dropIfExists('isp_hospitality_settings');
    }
};
