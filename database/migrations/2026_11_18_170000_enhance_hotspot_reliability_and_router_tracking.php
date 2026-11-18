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
        // 1. Routers: Permanent Identity & Network Move Tracking
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'last_known_ip')) {
                $table->string('last_known_ip', 64)->nullable()->after('ip');
            }
            if (!Schema::hasColumn('routers', 'network_changed_at')) {
                $table->timestamp('network_changed_at')->nullable()->after('last_known_ip');
            }
            if (!Schema::hasColumn('routers', 'router_serial')) {
                $table->string('router_serial', 64)->nullable()->after('network_changed_at');
            }
            if (!Schema::hasColumn('routers', 'router_mac')) {
                $table->string('router_mac', 32)->nullable()->after('router_serial');
            }
            if (!Schema::hasColumn('routers', 'router_identity')) {
                $table->string('router_identity', 128)->nullable()->after('router_mac');
            }
        });

        // 2. Hotspot Sales: Reliability, Router Attachment, Expiration & Pending State
        Schema::table('hotspot_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('hotspot_sales', 'router_id')) {
                $table->unsignedBigInteger('router_id')->nullable()->index()->after('tenant_id');
            }
            if (!Schema::hasColumn('hotspot_sales', 'phone')) {
                $table->string('phone', 32)->nullable()->index()->after('username');
            }
            if (!Schema::hasColumn('hotspot_sales', 'mac_address')) {
                $table->string('mac_address', 32)->nullable()->index()->after('phone');
            }
            if (!Schema::hasColumn('hotspot_sales', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('mac_address');
            }
            if (!Schema::hasColumn('hotspot_sales', 'status')) {
                $table->string('status', 32)->default('completed')->index()->after('payment_method');
            }
            if (!Schema::hasColumn('hotspot_sales', 'duration_minutes')) {
                $table->integer('duration_minutes')->default(60)->after('status');
            }
            if (!Schema::hasColumn('hotspot_sales', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('duration_minutes');
            }
            if (!Schema::hasColumn('hotspot_sales', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index()->after('started_at');
            }
            if (!Schema::hasColumn('hotspot_sales', 'sync_error')) {
                $table->text('sync_error')->nullable()->after('note');
            }
        });

        // 3. M-Pesa STK Transactions: Tenant & Router Isolation
        if (Schema::hasTable('mpesa_stk_transactions')) {
            Schema::table('mpesa_stk_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('mpesa_stk_transactions', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('mpesa_stk_transactions', 'router_id')) {
                    $table->unsignedBigInteger('router_id')->nullable()->index()->after('tenant_id');
                }
            });
        }

        // 4. Router Pending Activations Queue (Reliable Offline / Network Change Buffer)
        if (!Schema::hasTable('router_pending_activations')) {
            Schema::create('router_pending_activations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('router_id')->nullable()->index();
                $table->unsignedBigInteger('hotspot_sale_id')->nullable()->index();
                $table->string('voucher_code', 64)->index();
                $table->string('username', 100);
                $table->string('password', 100);
                $table->string('profile', 100)->nullable();
                $table->string('limit_uptime', 32)->nullable();
                $table->bigInteger('limit_bytes')->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->string('client_mac', 32)->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
                $table->integer('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['router_id', 'status']);
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_pending_activations');

        Schema::table('hotspot_sales', function (Blueprint $table) {
            $cols = ['router_id', 'phone', 'mac_address', 'ip_address', 'status', 'duration_minutes', 'started_at', 'expires_at', 'sync_error'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('hotspot_sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('routers', function (Blueprint $table) {
            $cols = ['last_known_ip', 'network_changed_at', 'router_serial', 'router_mac', 'router_identity'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('routers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('mpesa_stk_transactions')) {
            Schema::table('mpesa_stk_transactions', function (Blueprint $table) {
                $cols = ['tenant_id', 'router_id'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('mpesa_stk_transactions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

