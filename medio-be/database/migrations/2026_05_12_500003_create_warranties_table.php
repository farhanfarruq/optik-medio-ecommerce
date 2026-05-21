<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->string('warranty_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->date('purchase_date');
            $table->date('warranty_expires_at');
            $table->integer('warranty_months')->default(12);
            $table->string('status')->default('active');
            // active, expired, claimed, void
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['warranty_expires_at', 'status']);
        });

        Schema::create('service_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('warranty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('claim_type');
            // warranty_repair, lens_replacement, frame_adjustment, cleaning, other
            $table->string('status')->default('submitted');
            // submitted, reviewing, approved, in_progress, completed, rejected
            $table->text('description');
            $table->json('images')->nullable();
            $table->text('admin_notes')->nullable();
            $table->decimal('service_cost', 10, 2)->default(0);
            $table->boolean('is_covered_by_warranty')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_claims');
        Schema::dropIfExists('warranties');
    }
};
