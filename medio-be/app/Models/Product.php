<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'brand',
        'price', 'stock', 'weight', 'dimensions', 'variants', 'images', 'is_active', 'is_prescription_required',
    ];

    protected $casts = [
        'price'                    => 'decimal:2',
        'dimensions'               => 'array',
        'variants'                 => 'array',
        'images'                   => 'array',
        'is_active'                => 'boolean',
        'is_prescription_required' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
