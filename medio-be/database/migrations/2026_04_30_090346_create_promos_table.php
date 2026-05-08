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
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['buy_x_get_y', 'transaction_discount', 'product_discount']);
            
            // Buy X Get Y fields
            $table->foreignId('buy_product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->integer('buy_quantity')->nullable();
            $table->foreignId('get_product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->integer('get_quantity')->nullable();
            
            // Discount fields (transaction & product)
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->foreignId('discount_product_id')->nullable()->constrained('products')->onDelete('cascade');
            
            // Transaction fields
            $table->decimal('min_transaction_amount', 10, 2)->nullable();
            
            // General fields
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
