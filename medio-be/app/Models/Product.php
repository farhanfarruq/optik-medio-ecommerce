<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $appends = [
        'resolved_images',
        'resolved_variants',
    ];

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'brand',
        'price',
        'stock',
        'weight',
        'dimensions',
        'variants',
        'images',
        'tags',
        'is_active',
        'is_best_seller',
        'is_new',
        'is_not_for_sale',
        'is_prescription_required',
    ];

    protected $casts = [
        'price'                    => 'float',
        'stock'                    => 'integer',
        'weight'                   => 'integer',
        'dimensions'               => 'array',
        'variants'                 => 'array',
        'images'                   => 'array',
        'tags'                     => 'array',
        'is_active'                => 'boolean',
        'is_best_seller'           => 'boolean',
        'is_new'                   => 'boolean',
        'is_not_for_sale'          => 'boolean',
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

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('is_approved', true);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function activeProductVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function activeProductImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function buyPromos(): HasMany
    {
        return $this->hasMany(Promo::class, 'buy_product_id')->where('is_active', true);
    }

    public function discountPromos(): HasMany
    {
        return $this->hasMany(Promo::class, 'discount_product_id')->where('is_active', true);
    }

    public function buyPromosMany(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Promo::class, 'promo_buy_product');
    }

    public function discountPromosMany(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Promo::class, 'promo_discount_product');
    }

    protected function resolvedImages(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                if ($this->relationLoaded('activeProductImages') && $this->activeProductImages->isNotEmpty()) {
                    return $this->activeProductImages
                        ->pluck('image_path')
                        ->filter()
                        ->values()
                        ->all();
                }

                return is_array($this->images) ? $this->images : [];
            },
        );
    }

    protected function resolvedVariants(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                if ($this->relationLoaded('activeProductVariants') && $this->activeProductVariants->isNotEmpty()) {
                    return $this->activeProductVariants
                        ->map(fn (ProductVariant $variant) => [
                            'id' => $variant->id,
                            'sku' => $variant->sku,
                            'name' => $variant->name,
                            'color' => $variant->color,
                            'lens_size' => $variant->lens_size,
                            'stock' => $variant->stock,
                            'price' => (float) $variant->price,
                            'is_default' => $variant->is_default,
                            'attributes' => $variant->attributes,
                        ])
                        ->values()
                        ->all();
                }

                return is_array($this->variants) ? $this->variants : [];
            },
        );
    }

    public function primaryImagePath(): ?string
    {
        $resolvedImages = $this->resolved_images;

        return $resolvedImages[0] ?? null;
    }
}
