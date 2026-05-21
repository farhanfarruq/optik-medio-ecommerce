<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderPaymentChannel extends Command
{
    protected $signature   = 'orders:backfill-payment-channel {--dry-run : Tampilkan perubahan tanpa menyimpan}';
    protected $description = 'Backfill kolom payment_channel untuk semua order (transfer manual, COD, Xendit, dll)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('=== DRY RUN — tidak ada data yang disimpan ===');
        }

        // ── 1. Transfer manual: ambil dari bank.name ──────────────────────────
        $bankOrders = DB::select("
            SELECT o.id, o.order_number, b.name AS bank_name
            FROM orders o
            INNER JOIN banks b ON b.id = o.bank_id
            WHERE o.bank_id IS NOT NULL
              AND (o.payment_channel IS NULL OR o.payment_channel = '')
              AND o.deleted_at IS NULL
        ");

        $this->info('Transfer manual: ' . count($bankOrders) . ' order akan diupdate.');

        if (! $dryRun && count($bankOrders) > 0) {
            DB::statement("
                UPDATE orders o
                INNER JOIN banks b ON b.id = o.bank_id
                SET o.payment_channel = b.name
                WHERE o.bank_id IS NOT NULL
                  AND (o.payment_channel IS NULL OR o.payment_channel = '')
                  AND o.deleted_at IS NULL
            ");
        }

        foreach ($bankOrders as $row) {
            $this->line("  [Bank] #{$row->order_number} → {$row->bank_name}");
        }

        // ── 2. COD / Xendit / lainnya dari payment_methods ───────────────────
        $pmOrders = DB::select("
            SELECT o.id, o.order_number, pm.code, pm.name AS pm_name, p.provider
            FROM orders o
            INNER JOIN payments p ON p.order_id = o.id
            INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
            WHERE o.bank_id IS NULL
              AND (o.payment_channel IS NULL OR o.payment_channel = '')
              AND o.deleted_at IS NULL
        ");

        $this->info('COD/Xendit/lainnya: ' . count($pmOrders) . ' order akan diupdate.');

        if (! $dryRun && count($pmOrders) > 0) {
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
                  AND (o.payment_channel IS NULL OR o.payment_channel = '')
                  AND o.deleted_at IS NULL
            ");
        }

        foreach ($pmOrders as $row) {
            $label = match (true) {
                strtolower($row->code) === 'cod'                                                  => 'COD',
                str_contains(strtolower($row->code), 'xendit') || strtolower($row->provider) === 'xendit' => 'Xendit',
                default                                                                           => $row->pm_name,
            };
            $this->line("  [PM] #{$row->order_number} → {$label}");
        }

        // ── 3. Sisa order tanpa payment sama sekali ───────────────────────────
        $noPayment = DB::select("
            SELECT o.id, o.order_number
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.bank_id IS NULL
              AND p.id IS NULL
              AND (o.payment_channel IS NULL OR o.payment_channel = '')
              AND o.deleted_at IS NULL
        ");

        if (count($noPayment) > 0) {
            $this->warn(count($noPayment) . ' order tidak memiliki data payment — dilewati:');
            foreach ($noPayment as $row) {
                $this->line("  [SKIP] #{$row->order_number}");
            }
        }

        $total = count($bankOrders) + count($pmOrders);
        $this->newLine();
        $this->info("Selesai. Total diupdate: {$total}, Dilewati: " . count($noPayment));

        if ($dryRun) {
            $this->warn('Jalankan tanpa --dry-run untuk menyimpan perubahan.');
        }

        return self::SUCCESS;
    }
}
