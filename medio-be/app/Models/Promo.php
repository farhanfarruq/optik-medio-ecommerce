<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'buy_product_id',
        'buy_quantity',
        'get_product_id',
        'get_quantity',
        'discount_type',
        'discount_value',
        'discount_product_id',
        'min_transaction_amount',
        'start_date',
        'end_date',
        'is_active',
        'is_banner_active',
        'usage_limit_per_user',
        'buy_brands',
        'discount_brands',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_banner_active' => 'boolean',
        'usage_limit_per_user' => 'integer',
        'buy_brands' => 'array',
        'discount_brands' => 'array',
        'discount_value' => 'float',
        'min_transaction_amount' => 'float',
    ];

    public function buyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'buy_product_id');
    }

    public function getProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'get_product_id');
    }

    public function discountProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'discount_product_id');
    }

    public function buyProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promo_buy_product');
    }

    public function discountProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promo_discount_product');
    }
}
