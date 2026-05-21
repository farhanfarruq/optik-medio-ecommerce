<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'adjusted_by',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'reason',
        'notes',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'quantity_before'  => 'integer',
        'quantity_change'  => 'integer',
        'quantity_after'   => 'integer',
        'reference_id'     => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * Catat penyesuaian stok dan update stok produk dalam satu transaksi.
     */
    public static function adjust(
        Product $product,
        int $quantityChange,
        string $reason = 'manual_adjustment',
        ?string $notes = null,
        ?int $adjustedBy = null,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): self {
        $before = $product->stock;
        $after  = max(0, $before + $quantityChange);

        $product->update(['stock' => $after]);

        return self::create([
            'product_id'      => $product->id,
            'adjusted_by'     => $adjustedBy ?? auth()->id(),
            'quantity_before' => $before,
            'quantity_change' => $quantityChange,
            'quantity_after'  => $after,
            'reason'          => $reason,
            'notes'           => $notes,
            'reference_id'    => $referenceId,
            'reference_type'  => $referenceType,
        ]);
    }

    /**
     * Label human-readable untuk reason.
     */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'manual_adjustment' => 'Penyesuaian Manual',
            'order_placed'      => 'Pesanan Masuk',
            'order_cancelled'   => 'Pesanan Dibatalkan',
            'order_returned'    => 'Retur Pesanan',
            'import'            => 'Import Stok',
            'correction'        => 'Koreksi Stok',
            default             => ucfirst(str_replace('_', ' ', $this->reason)),
        };
    }
}
