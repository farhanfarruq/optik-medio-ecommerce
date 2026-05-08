<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedition_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expedition_id')->constrained('expeditions')->cascadeOnDelete();
            $table->string('service_code'); // e.g. 'REG', 'JTR', 'CTC'
            $table->string('service_name')->nullable(); // e.g. 'Reguler', 'JNE Trucking'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['expedition_id', 'service_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedition_services');
    }
};
