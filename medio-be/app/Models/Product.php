<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'meta_title',
        'meta_description',
        'canonical_slug',
        'og_image',
        'sku',
        'description',
        'brand',
        'gender',
        'frame_shape',
        'frame_material',
        'frame_color',
        'face_size_fit',
        'price',
        'stock',
        'low_stock_threshold',
        'weight',
        'dimensions',
        'lens_width',
        'bridge_width',
        'temple_length',
        'frame_width',
        'variants',
        'images',
        'tags',
        'campaign_tags',
        'google_product_category',
        'gtin',
        'mpn',
        'condition',
        'is_active',
        'is_best_seller',
        'is_featured',
        'recommendation_priority',
        'is_new',
        'is_not_for_sale',
        'is_prescription_required',
        'prescription_rules',
    ];

    protected $casts = [
        'price'                    => 'float',
        'stock'                    => 'integer',
        'low_stock_threshold'      => 'integer',
        'weight'                   => 'integer',
        'lens_width'               => 'integer',
        'bridge_width'             => 'integer',
        'temple_length'            => 'integer',
        'frame_width'              => 'integer',
        'dimensions'               => 'array',
        'variants'                 => 'array',
        'images'                   => 'array',
        'tags'                     => 'array',
        'campaign_tags'            => 'array',
        'is_active'                => 'boolean',
        'is_best_seller'           => 'boolean',
        'is_featured'              => 'boolean',
        'recommendation_priority'  => 'integer',
        'is_new'                   => 'boolean',
        'is_not_for_sale'          => 'boolean',
        'is_prescription_required' => 'boolean',
        'prescription_rules'       => 'array',
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

    public function lensCompatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class, 'frame_product_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * Cek apakah stok produk di bawah threshold.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= ($this->low_stock_threshold ?? 5);
    }

    public function compatibleLensOptions(): BelongsToMany
    {
        return $this->belongsToMany(LensOption::class, 'product_compatibilities', 'frame_product_id', 'lens_option_id')
            ->withPivot('compatibility_rule')
            ->withTimestamps();
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
