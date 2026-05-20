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

        // Backfill data yang sudah ada.
        //
        // Catatan kompatibilitas (fix Phase 1+):
        // Sebelumnya migration ini pakai `UPDATE ... INNER JOIN` (ekstensi MySQL)
        // yang menyebabkan migration crash di SQLite (env test). Sekarang kita
        // pakai pendekatan driver-aware: MySQL/MariaDB pakai JOIN native (cepat
        // untuk dataset besar di production), SQLite/Postgres pakai sub-query
        // yang ANSI-compliant.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL fast-path: UPDATE ... INNER JOIN
            DB::statement("
                UPDATE orders o
                INNER JOIN banks b ON b.id = o.bank_id
                SET o.payment_channel = b.name
                WHERE o.bank_id IS NOT NULL
                  AND o.deleted_at IS NULL
            ");

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
        } else {
            // Cross-DB path (SQLite / Postgres / lainnya) — pakai sub-query ANSI.
            //
            // 1. Transfer manual: ambil dari banks.name
            DB::statement("
                UPDATE orders
                SET payment_channel = (
                    SELECT b.name FROM banks b WHERE b.id = orders.bank_id
                )
                WHERE bank_id IS NOT NULL
                  AND deleted_at IS NULL
            ");

            // 2. COD / Xendit / lainnya: derive dari payment_methods via payments.
            //    Loop manual karena CASE expression di sub-query susah di SQLite.
            $orders = DB::table('orders')
                ->whereNull('bank_id')
                ->whereNull('payment_channel')
                ->whereNull('deleted_at')
                ->select('id')
                ->get();

            foreach ($orders as $order) {
                $payment = DB::table('payments as p')
                    ->join('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
                    ->where('p.order_id', $order->id)
                    ->select('pm.code', 'pm.name', 'p.provider')
                    ->first();

                if (!$payment) {
                    continue;
                }

                $code = strtolower((string) ($payment->code ?? ''));
                $provider = strtolower((string) ($payment->provider ?? ''));

                $channel = match (true) {
                    $code === 'cod' => 'COD',
                    str_contains($code, 'xendit') || $provider === 'xendit' => 'Xendit',
                    default => $payment->name,
                };

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['payment_channel' => $channel]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_channel');
        });
    }
};
