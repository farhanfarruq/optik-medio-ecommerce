<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_price',
        'quantity', 'weight', 'variant', 'subtotal', 'prescription', 'parent_item_id',
        'lens_option_id', 'lens_coating_id', 'prescription_profile_id',
        'lens_price', 'coating_price', 'configuration_snapshot',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal'      => 'decimal:2',
        'variant'       => 'array',
        'prescription'  => 'array',
        'lens_price'    => 'decimal:2',
        'coating_price' => 'decimal:2',
        'configuration_snapshot' => 'array',
    ];

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'parent_item_id');
    }

    public function subItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class, 'parent_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lensOption(): BelongsTo
    {
        return $this->belongsTo(LensOption::class);
    }

    public function lensCoating(): BelongsTo
    {
        return $this->belongsTo(LensCoating::class);
    }

    public function prescriptionProfile(): BelongsTo
    {
        return $this->belongsTo(PrescriptionProfile::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class, 'order_item_id');
    }
}
