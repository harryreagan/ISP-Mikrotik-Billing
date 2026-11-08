<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hub_outbox')) {
            Schema::create('hub_outbox', function (Blueprint $table) {
                $table->id();
                $table->string('endpoint');
                $table->string('method')->default('POST');
                $table->json('payload');
                $table->integer('attempts')->default(0);
                $table->enum('status', ['pending', 'failed', 'sent'])->default('pending');
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_outbox');
    }
};
