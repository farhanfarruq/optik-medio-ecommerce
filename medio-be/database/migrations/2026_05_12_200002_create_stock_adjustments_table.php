<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('quantity_before');
            $table->integer('quantity_change'); // positif = tambah, negatif = kurang
            $table->integer('quantity_after');
            $table->string('reason')->default('manual_adjustment');
            // reason: manual_adjustment, order_placed, order_cancelled, order_returned, import, correction
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable(); // order_id jika terkait order
            $table->string('reference_type')->nullable(); // 'order', 'return', dll
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['adjusted_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
