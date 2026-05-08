<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_proof_image')->nullable()->after('tracking_number');
            $table->boolean('is_payment_verified')->default(false)->after('payment_proof_image');
            $table->foreignId('verified_by')->nullable()->after('is_payment_verified')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['payment_proof_image', 'is_payment_verified', 'payment_verified_at']);
        });
    }
};
