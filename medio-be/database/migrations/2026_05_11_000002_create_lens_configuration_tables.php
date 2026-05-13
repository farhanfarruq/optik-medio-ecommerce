<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->decimal('base_price', 15, 2)->default(0);
            $table->json('prescription_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('lens_coatings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('product_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('lens_option_id')->constrained('lens_options')->cascadeOnDelete();
            $table->json('compatibility_rule')->nullable();
            $table->timestamps();

            $table->unique(['frame_product_id', 'lens_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_compatibilities');
        Schema::dropIfExists('lens_coatings');
        Schema::dropIfExists('lens_options');
    }
};
