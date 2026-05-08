<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_closes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_closes');
    }
};
