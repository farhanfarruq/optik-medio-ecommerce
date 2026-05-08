<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('base_amount', 15, 2);
            $table->decimal('commission_rate_percentage', 5, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'source_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_details');
    }
};
