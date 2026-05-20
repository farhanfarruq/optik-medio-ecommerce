<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteDeliveredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete-delivered
                            {--days=3 : Jumlah hari minimum sejak delivered sebelum di-complete}
                            {--dry-run : Tampilkan order yang akan diproses tanpa mengubah status}
                            {--chunk=100 : Jumlah order yang diproses per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete semua order lama yang statusnya "delivered" lebih dari N hari (default: 3 hari)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $dryRun  = (bool) $this->option('dry-run');
        $chunk   = (int) $this->option('chunk');
        $cutoff  = now()->subDays($days);

        $this->info("=== Auto-Complete Delivered Orders ===");
        $this->info("Cutoff  : {$cutoff->toDateTimeString()} (lebih dari {$days} hari lalu)");
        $this->info("Mode    : " . ($dryRun ? '🔍 DRY RUN (tidak ada perubahan)' : '✅ LIVE (akan mengubah status)'));
        $this->newLine();

        // Query order yang eligible:
        // 1. Status masih 'delivered'
        // 2. delivered_at <= cutoff ATAU (delivered_at null DAN updated_at <= cutoff)
        //    → order lama yang delivered_at-nya tidak terisi, pakai updated_at sebagai fallback
        // 3. Tidak ada return request aktif
        // 4. Tidak ada komplain aktif
        $query = Order::with(['user'])
            ->where('status', 'delivered')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($inner) use ($cutoff) {
                    // Order yang punya delivered_at dan sudah lewat cutoff
                    $inner->whereNotNull('delivered_at')
                          ->where('delivered_at', '<=', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    // Order lama yang delivered_at-nya null, pakai updated_at sebagai fallback
                    $inner->whereNull('delivered_at')
                          ->where('updated_at', '<=', $cutoff);
                });
            })
            ->whereDoesntHave('returnRequest', fn ($q) => $q
                ->whereIn('status', ['pending', 'approved']))
            ->whereDoesntHave('complains', fn ($q) => $q
                ->whereIn('status', ['open', 'in_progress']));

        $total = $query->count();

        if ($total === 0) {
            $this->info("✅ Tidak ada order yang perlu di-complete.");
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$total} order yang akan diproses.");
        $this->newLine();

        if ($dryRun) {
            // Tampilkan daftar order tanpa mengubah apapun
            $this->table(
                ['ID', 'Order Number', 'User', 'delivered_at', 'updated_at', 'Status'],
                $query->limit(50)->get()->map(fn ($o) => [
                    $o->id,
                    $o->order_number,
                    $o->user?->name ?? '-',
                    $o->delivered_at?->toDateTimeString() ?? '(null)',
                    $o->updated_at?->toDateTimeString() ?? '-',
                    $o->status,
                ])
            );

            if ($total > 50) {
                $this->warn("... dan " . ($total - 50) . " order lainnya (hanya 50 pertama ditampilkan).");
            }

            $this->newLine();
            $this->info("Dry run selesai. Jalankan tanpa --dry-run untuk menerapkan perubahan.");
            return self::SUCCESS;
        }

        // Konfirmasi jika dijalankan secara interaktif
        if ($this->input->isInteractive()) {
            if (! $this->confirm("Lanjutkan mengubah {$total} order ke status 'completed'?")) {
                $this->warn("Dibatalkan.");
                return self::SUCCESS;
            }
        }

        $processed = 0;
        $failed    = 0;
        $bar       = $this->output->createProgressBar($total);
        $bar->start();

        // Proses per chunk agar tidak overload memory
        $query->chunkById($chunk, function ($orders) use (&$processed, &$failed, $bar) {
            foreach ($orders as $order) {
                try {
                    $updateData = ['status' => 'completed'];

                    // Isi delivered_at jika masih null (pakai updated_at sebagai estimasi)
                    if ($order->delivered_at === null) {
                        $updateData['delivered_at'] = $order->updated_at;
                    }

                    $order->update($updateData);

                    Log::info('orders:auto-complete-delivered — order completed', [
                        'order_id'     => $order->id,
                        'order_number' => $order->order_number,
                        'delivered_at' => $order->delivered_at?->toDateTimeString(),
                        'updated_at'   => $order->updated_at?->toDateTimeString(),
                    ]);

                    $processed++;
                } catch (\Throwable $e) {
                    Log::error('orders:auto-complete-delivered — gagal complete order', [
                        'order_id' => $order->id,
                        'error'    => $e->getMessage(),
                    ]);
                    $failed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Selesai!");
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Total ditemukan', $total],
                ['Berhasil di-complete', $processed],
                ['Gagal', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
