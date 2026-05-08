<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expedition_id')->constrained()->cascadeOnDelete();
            $table->string('service_name')->default('Regular');
            $table->string('service_code')->default('REG');
            $table->string('province');
            $table->string('province_id')->nullable();
            $table->string('city');
            $table->string('city_id')->nullable();
            $table->string('district');
            $table->string('district_id')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('etd')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['province_id', 'city_id', 'district_id']);
            $table->index(['province', 'city', 'district']);
            $table->index(['is_active', 'service_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
