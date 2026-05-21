<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('address');
            $table->string('city');
            $table->string('province');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('maps_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('operating_hours')->nullable();
            // { "mon": "09:00-18:00", "tue": "09:00-18:00", ... }
            $table->integer('appointment_capacity')->default(10);
            // maks appointment per hari
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('store_branches')->onDelete('cascade');
            $table->date('date');
            $table->integer('capacity_override')->nullable();
            // null = pakai default branch capacity
            $table->boolean('is_closed')->default(false);
            $table->string('close_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_schedules');
        Schema::dropIfExists('store_branches');
    }
};
