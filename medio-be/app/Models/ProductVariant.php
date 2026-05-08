<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'color',
        'lens_size',
        'stock',
        'price',
        'sort_order',
        'is_default',
        'is_active',
        'attributes',
    ];

    protected $casts = [
        'stock' => 'integer',
        'price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
}
