<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function getAll(array $filters = [])
    {
        $query = Product::with([
                'category',
                'buyPromos.getProduct',
                'discountPromos',
                'buyPromosMany',
                'discountPromosMany',
                'activeProductVariants',
                'activeProductImages',
            ])
            ->withCount([
                'approvedReviews as review_count',
            ])
            ->withAvg([
                'approvedReviews as avg_rating',
            ], 'rating')
            ->withSum([
                'orderItems as purchase_count' => fn ($q) => $q->whereHas('order', fn ($orderQuery) => $orderQuery
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])),
            ], 'quantity')
            ->where('is_active', true);

        if (!empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        foreach (['gender', 'frame_shape', 'frame_material', 'frame_color', 'face_size_fit'] as $attribute) {
            if (!empty($filters[$attribute])) {
                $query->where($attribute, $filters[$attribute]);
            }
        }

        if (!empty($filters['prescription_supported']) && $filters['prescription_supported'] === 'true') {
            $query->where('is_prescription_required', true);
        }

        if (!empty($filters['featured']) && $filters['featured'] === 'true') {
            $query->where('is_featured', true);
        }

        if (!empty($filters['exclude_not_for_sale']) && $filters['exclude_not_for_sale'] === 'true') {
            $query->where('is_not_for_sale', false);
        }

        if (!empty($filters['only_not_for_sale']) && $filters['only_not_for_sale'] === 'true') {
            $query->where('is_not_for_sale', true);
        }

        if (!empty($filters['campaign_tag'])) {
            $campaignTag = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim((string) $filters['campaign_tag']));
            $query->where('campaign_tags', 'like', '%' . $campaignTag . '%');
        }

        if (!empty($filters['in_stock_only']) && $filters['in_stock_only'] === 'true') {
            $query->where('stock', '>', 0);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['search']));
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('brand', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%')
                  ->orWhere('tags', 'like', '%' . $search . '%')
                  ->orWhere('campaign_tags', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['promo_id'])) {
            $promoId = $filters['promo_id'];
            $promo = \App\Models\Promo::find($promoId);
            if ($promo) {
                $query->where(function($q) use ($promo) {
                    $q->where('id', $promo->buy_product_id)
                      ->orWhere('id', $promo->discount_product_id)
                      ->orWhereHas('buyPromosMany', fn($pq) => $pq->where('promos.id', $promo->id))
                      ->orWhereHas('discountPromosMany', fn($pq) => $pq->where('promos.id', $promo->id));
                    
                    if (!empty($promo->buy_brands)) {
                        $q->orWhereIn('brand', $promo->buy_brands);
                    }
                    if (!empty($promo->discount_brands)) {
                        $q->orWhereIn('brand', $promo->discount_brands);
                    }
                });
            }
        }

        if (!empty($filters['has_promo']) && $filters['has_promo'] == 'true') {
            $query->where(function($q) {
                $now = now();
                $q->whereHas('buyPromos', fn($pq) => $pq->where('start_date', '<=', $now)->where('end_date', '>=', $now))
                  ->orWhereHas('discountPromos', fn($pq) => $pq->where('start_date', '<=', $now)->where('end_date', '>=', $now))
                  ->orWhereHas('buyPromosMany', fn($pq) => $pq->where('is_active', true)->where('start_date', '<=', $now)->where('end_date', '>=', $now))
                  ->orWhereHas('discountPromosMany', fn($pq) => $pq->where('is_active', true)->where('start_date', '<=', $now)->where('end_date', '>=', $now));
                
                // For brands, it's a bit more complex as it's JSON
                $activePromos = \App\Models\Promo::where('is_active', true)->where('start_date', '<=', $now)->where('end_date', '>=', $now)->get();
                $brands = [];
                foreach ($activePromos as $ap) {
                    if (!empty($ap->buy_brands)) $brands = array_merge($brands, $ap->buy_brands);
                    if (!empty($ap->discount_brands)) $brands = array_merge($brands, $ap->discount_brands);
                }
                if (!empty($brands)) {
                    $q->orWhereIn('brand', array_unique($brands));
                }
            });
        }

        if (!empty($filters['prioritize_glasses']) && $filters['prioritize_glasses'] === 'true') {
            $glassesCategoryIds = Category::whereIn('slug', [
                'kacamata-frame',
                'frame-kacamata',
                'kacamata-hitam',
                'kacamata-baca',
            ])->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (!empty($glassesCategoryIds)) {
                $query->orderByRaw('CASE WHEN category_id IN (' . implode(',', $glassesCategoryIds) . ') THEN 0 ELSE 1 END');
            }
        }

        match ($filters['sort'] ?? 'latest') {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('recommendation_priority')->orderByDesc('purchase_count'),
            'best_seller' => $query->orderByDesc('is_best_seller')->orderByDesc('recommendation_priority')->orderByDesc('purchase_count'),
            'rating' => $query->orderByDesc('avg_rating'),
            'popular' => $query->orderByDesc('recommendation_priority')->orderByDesc('purchase_count'),
            default => $query->latest(),
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 24), 1), 48);

        return $query->paginate($perPage);
    }

    public function findById(int $id)
    {
        return Product::with([
                'category',
                'buyPromos.getProduct',
                'discountPromos',
                'buyPromosMany',
                'discountPromosMany',
                'activeProductVariants',
                'activeProductImages',
            ])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->withSum([
                'orderItems as purchase_count' => fn ($q) => $q->whereHas('order', fn ($orderQuery) => $orderQuery
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])),
            ], 'quantity')
            ->findOrFail($id);
    }

    public function findBySlug(string $slug)
    {
        return Product::with([
                'category',
                'buyPromos.getProduct',
                'discountPromos',
                'buyPromosMany',
                'discountPromosMany',
                'activeProductVariants',
                'activeProductImages',
            ])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->withSum([
                'orderItems as purchase_count' => fn ($q) => $q->whereHas('order', fn ($orderQuery) => $orderQuery
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])),
            ], 'quantity')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update(int $id, array $data)
    {
        $product = $this->findById($id);
        $product->update($data);
        return $product;
    }

    public function delete(int $id)
    {
        return Product::destroy($id);
    }

    public function decrementStock(int $id, int $quantity): void
    {
        Product::where('id', $id)->decrement('stock', $quantity);
    }
}
