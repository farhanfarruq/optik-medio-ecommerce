<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * P1-6 (Phase 3): extracted dari OrderController::cancel inline transaction.
 *
 * Tanggung jawab tunggal: kembalikan stok produk saat order di-cancel.
 *
 * Pakai lockForUpdate untuk konsisten dengan DecrementProductStockAction.
 * Harus dipanggil di dalam DB::transaction caller.
 */
class RestoreProductStockAction
{
    /**
     * Kembalikan stok semua item pesanan ke catalog Product.
     *
     * Mengikuti behavior asli OrderController::cancel — increment stock
     * sebanyak quantity yang sebelumnya di-decrement. Skip item yang
     * punya `parent_item_id` (lensa link / addon) karena mereka tidak
     * mengurangi stok produk catalog secara independen.
     */
    public function execute(Order $order): void
    {
        $items = $order->items;
        if ($items->isEmpty()) {
            return;
        }

        // Hanya item yang TIDAK punya parent_item_id yang punya stok independen.
        $standaloneItems = $items->filter(fn ($item) => !$item->parent_item_id);

        $productIds = $standaloneItems->pluck('product_id')->filter()->unique()->values()->all();
        if (empty($productIds)) {
            return;
        }

        // Lock semua produk yang akan di-restore.
        Product::whereIn('id', $productIds)->lockForUpdate()->get(['id']);

        foreach ($standaloneItems as $item) {
            if (!$item->product_id) {
                continue;
            }
            Product::where('id', $item->product_id)->increment('stock', $item->quantity);
        }
    }
}
