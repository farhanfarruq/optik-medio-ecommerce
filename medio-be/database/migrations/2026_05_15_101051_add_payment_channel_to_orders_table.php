<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kolom untuk menyimpan label metode pembayaran:
            // nama bank (transfer manual), 'COD', 'Xendit', atau nama payment method lain
            $table->string('payment_channel', 100)->nullable()->after('bank_id');
        });

        // Backfill data yang sudah ada
        // 1. Transfer manual: ambil dari bank.name
        DB::statement("
            UPDATE orders o
            INNER JOIN banks b ON b.id = o.bank_id
            SET o.payment_channel = b.name
            WHERE o.bank_id IS NOT NULL
              AND o.deleted_at IS NULL
        ");

        // 2. COD & Xendit & lainnya: ambil dari payment_methods.code / name
        DB::statement("
            UPDATE orders o
            INNER JOIN payments p ON p.order_id = o.id
            INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
            SET o.payment_channel = CASE
                WHEN LOWER(pm.code) = 'cod' THEN 'COD'
                WHEN LOWER(pm.code) LIKE '%xendit%' OR LOWER(p.provider) = 'xendit' THEN 'Xendit'
                ELSE pm.name
            END
            WHERE o.bank_id IS NULL
              AND o.payment_channel IS NULL
              AND o.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_channel');
        });
    }
};
