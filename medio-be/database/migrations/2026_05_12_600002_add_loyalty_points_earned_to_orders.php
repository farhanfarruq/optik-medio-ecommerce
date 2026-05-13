<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Sudah ada di order model tapi mungkin belum di DB
            if (! Schema::hasColumn('orders', 'loyalty_points_earned')) {
                $table->integer('loyalty_points_earned')->default(0)->after('loyalty_discount_amount');
            }
            if (! Schema::hasColumn('orders', 'review_requested_at')) {
                $table->timestamp('review_requested_at')->nullable()->after('delivered_at')
                    ->comment('Kapan email request review dikirim');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_earned', 'review_requested_at']);
        });
    }
};
