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
        // 1. Unmatched and Raw Payment Messages Table
        if (! Schema::hasTable('unmatched_payments')) {
            Schema::create('unmatched_payments', function (Blueprint $table) {
                $table->id();
                $table->string('source')->default('mpesa_sms'); // mpesa_sms, daraja, payhero, kopokopo
                $table->string('sender', 100)->nullable(); // e.g. MPESA
                $table->string('transaction_code', 100)->unique();
                $table->decimal('amount', 12, 2);
                $table->string('phone', 50)->nullable();
                $table->string('sender_name', 255)->nullable();
                $table->string('account_reference', 255)->nullable();
                $table->text('raw_message')->nullable();
                $table->json('raw_payload')->nullable();
                $table->enum('status', ['pending', 'matched', 'manual_matched', 'ignored'])->default('pending');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_unique_id', 100)->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('phone');
                $table->index('account_reference');
            });
        }

        // 2. Add unique constraint or index to collection_summaries.transaction_id
        if (Schema::hasTable('collection_summaries')) {
            $existingIndexes = collect(Schema::getIndexes('collection_summaries'))->pluck('name')->toArray();
            if (! in_array('collection_summaries_transaction_id_index', $existingIndexes)) {
                Schema::table('collection_summaries', function (Blueprint $table) {
                    $table->index('transaction_id', 'collection_summaries_transaction_id_index');
                });
            }
        }

        // 3. Ensure Daraja / STK Push logging table exists
        if (! Schema::hasTable('mpesa_stk_transactions')) {
            Schema::create('mpesa_stk_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('gateway')->default('daraja'); // daraja, payhero, kopokopo
                $table->string('merchant_request_id')->nullable()->index();
                $table->string('checkout_request_id')->nullable()->unique();
                $table->string('mpesa_receipt_number')->nullable()->unique();
                $table->decimal('amount', 12, 2);
                $table->string('phone', 50);
                $table->string('reference')->nullable();
                $table->string('customer_unique_id')->nullable()->index();
                $table->integer('result_code')->nullable();
                $table->text('result_desc')->nullable();
                $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'timeout'])->default('pending');
                $table->json('raw_callback')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unmatched_payments');
        Schema::dropIfExists('mpesa_stk_transactions');
    }
};
