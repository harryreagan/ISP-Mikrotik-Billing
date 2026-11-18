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
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'plan_slug')) {
                    $table->string('plan_slug', 50)->default('starter')->after('settings');
                }
                if (!Schema::hasColumn('tenants', 'subscription_status')) {
                    $table->string('subscription_status', 30)->default('trial')->after('plan_slug');
                }
                if (!Schema::hasColumn('tenants', 'subscription_expires_at')) {
                    $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
                }
                if (!Schema::hasColumn('tenants', 'subscription_started_at')) {
                    $table->timestamp('subscription_started_at')->nullable()->after('subscription_expires_at');
                }
                if (!Schema::hasColumn('tenants', 'router_limit')) {
                    $table->unsignedInteger('router_limit')->default(2)->after('subscription_started_at');
                }
                if (!Schema::hasColumn('tenants', 'customer_limit')) {
                    $table->unsignedInteger('customer_limit')->default(150)->after('router_limit');
                }
            });
        }

        if (!Schema::hasTable('tenant_subscription_payments')) {
            Schema::create('tenant_subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('plan_slug', 50)->default('starter');
                $table->string('billing_cycle', 20)->default('monthly');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('KES');
                $table->string('gateway', 30)->default('payhero'); // payhero, paystack, manual, mpesa
                $table->string('reference', 80)->unique();
                $table->string('external_reference', 120)->nullable()->index();
                $table->string('phone', 25)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('status', 30)->default('pending')->index(); // pending, completed, failed, cancelled
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_payments');

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                $columns = ['customer_limit', 'router_limit', 'subscription_started_at', 'subscription_expires_at', 'subscription_status', 'plan_slug'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('tenants', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

