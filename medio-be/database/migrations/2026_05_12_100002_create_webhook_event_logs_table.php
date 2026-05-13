<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('xendit'); // xendit, manual, etc.
            $table->string('event_type')->nullable();      // PAID, EXPIRED, FAILED
            $table->string('idempotency_key')->unique();   // transaction_id + status
            $table->string('external_id')->nullable()->index();
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->string('processing_status')->default('received'); // received, processed, skipped, failed
            $table->text('processing_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'external_id']);
            $table->index(['processing_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_event_logs');
    }
};
