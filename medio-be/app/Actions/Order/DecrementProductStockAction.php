<?php

namespace App\Actions\Order;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P1-6 (Phase 3): extracted dari OrderController::store inline transaction.
 *
 * Tanggung jawab tunggal: lock + recheck + decrement stok untuk semua item
 * pesanan dengan defense-in-depth (lockForUpdate + atomic CAS).
 *
 * Harus dipanggil DI DALAM DB::transaction caller — action ini tidak open
 * transaction sendiri, agar caller bisa control rollback scope yang lebih luas
 * (mis. stock decrement + order create + log harus atomic bersama).
 *
 * Throws ValidationException kalau stok berubah saat checkout.
 */
class DecrementProductStockAction
{
    /**
     * @param array<int, array<string, mixed>> $items
     *        Setiap item harus punya: product_id (int), quantity (int),
     *        dan optional linked_item_index (kalau diset, item dilewati
     *        karena lensa link tidak punya stok independen).
     */
    public function execute(array $items): void
    {
        if (empty($items)) {
            return;
        }

        // ── 1. Lock semua produk yang akan di-decrement ──
        // Mencegah race condition: 2 user checkout bersamaan untuk produk
        // dengan stok tipis.
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return;
        }

        Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->get(['id', 'stock', 'name']);

        // ── 2. Re-check stok dengan data segar (defense-in-depth) ──
        $lockedProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $isLinkedLens = isset($item['linked_item_index']);
            if ($isLinkedLens) {
                continue; // lensa link tidak punya stok independen
            }

            $locked = $lockedProducts->get($item['product_id']);
            if (!$locked || $locked->stock < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Stok produk "' . ($locked->name ?? '#' . $item['product_id'])
                        . '" berubah saat checkout. Silakan periksa keranjang kembali.',
                    ],
                ]);
            }
        }

        // ── 3. Decrement dengan atomic CAS sebagai second-line defense ──
        // Pattern WHERE stock >= qty DECREMENT memastikan walaupun lock
        // somehow terlewat (mis. driver issue), decrement tetap aman.
        // Mengikuti behavior asli OrderController (tidak skip linked lens).
        foreach ($items as $item) {
            $updated = Product::where('id', $item['product_id'])
                ->where('stock', '>=', $item['quantity'])
                ->decrement('stock', $item['quantity']);

            if ($updated === 0) {
                throw ValidationException::withMessages([
                    'items' => ['Stok produk berubah saat checkout. Silakan periksa keranjang kembali.'],
                ]);
            }
        }
    }
}
