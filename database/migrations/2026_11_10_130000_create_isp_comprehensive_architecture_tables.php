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
        // 1. Ensure phone & OTP columns on users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'mobile')) {
                    $table->string('mobile')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('users', 'address')) {
                    $table->string('address')->nullable()->after('mobile');
                }
                if (! Schema::hasColumn('users', 'otp_code')) {
                    $table->string('otp_code', 10)->nullable()->after('password');
                }
                if (! Schema::hasColumn('users', 'otp_expires_at')) {
                    $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
                }
                if (! Schema::hasColumn('users', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
                }
                if (! Schema::hasColumn('users', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
                }
            });
        }

        // 1b. Add tenant_id & duration columns to package_lists
        if (Schema::hasTable('package_lists')) {
            Schema::table('package_lists', function (Blueprint $table) {
                if (! Schema::hasColumn('package_lists', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('package_lists', 'duration_value')) {
                    $table->unsignedInteger('duration_value')->default(1)->after('price');
                }
                if (! Schema::hasColumn('package_lists', 'duration_unit')) {
                    $table->string('duration_unit', 20)->default('days')->after('duration_value');
                }
                if (! Schema::hasColumn('package_lists', 'data_cap_mb')) {
                    $table->unsignedInteger('data_cap_mb')->nullable()->after('duration_unit');
                }
                if (! Schema::hasColumn('package_lists', 'shared_users')) {
                    $table->unsignedInteger('shared_users')->default(1)->after('data_cap_mb');
                }
                if (! Schema::hasColumn('package_lists', 'validity_type')) {
                    $table->string('validity_type', 32)->default('from_purchase')->after('shared_users');
                }
            });
        }

        // 1c. Add tenant_id, service_type, and router_id to customers_infos
        if (Schema::hasTable('customers_infos')) {
            Schema::table('customers_infos', function (Blueprint $table) {
                if (! Schema::hasColumn('customers_infos', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('customers_infos', 'service_type')) {
                    $table->string('service_type', 32)->default('pppoe')->after('status');
                }
                if (! Schema::hasColumn('customers_infos', 'router_id')) {
                    $table->unsignedBigInteger('router_id')->nullable()->index()->after('package_id');
                }
            });
        }

        // 2. Ensure phone support on login_otp_codes
        if (Schema::hasTable('login_otp_codes')) {
            Schema::table('login_otp_codes', function (Blueprint $table) {
                if (! Schema::hasColumn('login_otp_codes', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }
                if (! Schema::hasColumn('login_otp_codes', 'action_type')) {
                    $table->string('action_type', 40)->default('auth')->after('phone');
                }
            });
        }

        // 3. Notification templates per ISP Tenant
        if (! Schema::hasTable('isp_notification_templates')) {
            Schema::create('isp_notification_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('event_key', 64);
                $table->string('title', 255);
                $table->boolean('sms_enabled')->default(true);
                $table->text('sms_template')->nullable();
                $table->boolean('email_enabled')->default(true);
                $table->string('email_subject', 255)->nullable();
                $table->longText('email_template')->nullable();
                $table->boolean('in_app_enabled')->default(true);
                $table->text('in_app_template')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'event_key']);
            });
        }

        // 4. Notification delivery log per ISP Tenant
        if (! Schema::hasTable('isp_notification_logs')) {
            Schema::create('isp_notification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('event_key', 64);
                $table->enum('channel', ['sms', 'email', 'in_app']);
                $table->string('recipient', 255);
                $table->string('subject', 255)->nullable();
                $table->text('content');
                $table->enum('status', ['queued', 'sent', 'failed', 'delivered'])->default('queued');
                $table->string('provider', 64)->nullable();
                $table->text('provider_response')->nullable();
                $table->decimal('cost', 8, 4)->default(0.0000);
                $table->unsignedInteger('retry_count')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index('recipient');
            });
        }

        // 5. Captive Portal Customization Settings per ISP Tenant
        if (! Schema::hasTable('isp_portal_settings')) {
            Schema::create('isp_portal_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->string('brand_name', 255)->nullable();
                $table->string('brand_color', 32)->default('#ff6600');
                $table->string('secondary_color', 32)->default('#1e293b');
                $table->string('logo_url', 500)->nullable();
                $table->string('background_url', 500)->nullable();
                $table->string('headline', 255)->default('Welcome to High-Speed WiFi');
                $table->text('subheadline')->nullable();
                $table->text('terms_text')->nullable();
                $table->string('support_phone', 64)->nullable();
                $table->string('support_whatsapp', 64)->nullable();
                $table->string('theme_layout', 32)->default('cards'); // cards, list, minimal
                $table->boolean('free_trial_enabled')->default(false);
                $table->string('free_trial_duration', 32)->default('15m');
                $table->string('payment_instructions', 500)->nullable();
                $table->json('walled_garden_domains')->nullable();
                $table->timestamps();
            });
        }

        // 6. Router Alerts table for deduplication and thresholds
        if (! Schema::hasTable('isp_router_alerts')) {
            Schema::create('isp_router_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('router_id')->index();
                $table->string('alert_type', 64); // offline, high_cpu, high_memory
                $table->string('message', 500);
                $table->timestamp('last_alerted_at')->nullable();
                $table->unsignedInteger('alert_count')->default(1);
                $table->boolean('is_resolved')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->unique(['router_id', 'alert_type', 'is_resolved'], 'uniq_router_alert');
            });
        }

        // 7. Audit log table per ISP Tenant
        if (! Schema::hasTable('isp_audit_logs')) {
            Schema::create('isp_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 255)->nullable();
                $table->string('action', 64);
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'entity_type']);
            });
        }

        // 8. Custom SMS Gateway configs per ISP Tenant
        if (! Schema::hasTable('isp_sms_gateways')) {
            Schema::create('isp_sms_gateways', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('gateway_type', 40); // system, africastalking, blessedtexts, celcom, advanta, textsms, mobitech, twilio, generic_http
                $table->string('sender_id', 64)->nullable();
                $table->text('api_key')->nullable(); // encrypted
                $table->text('api_secret')->nullable(); // encrypted
                $table->string('api_username', 255)->nullable();
                $table->string('endpoint_url', 500)->nullable();
                $table->string('http_method', 10)->default('POST');
                $table->json('http_headers')->nullable();
                $table->text('body_template')->nullable();
                $table->string('success_keyword', 100)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_fallback')->default(false);
                $table->decimal('balance', 12, 2)->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_status', 32)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_default']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_sms_gateways');
        Schema::dropIfExists('isp_audit_logs');
        Schema::dropIfExists('isp_router_alerts');
        Schema::dropIfExists('isp_portal_settings');
        Schema::dropIfExists('isp_notification_logs');
        Schema::dropIfExists('isp_notification_templates');
    }
};
