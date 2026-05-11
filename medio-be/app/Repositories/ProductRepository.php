<?php

namespace App\Repositories;

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
                  ->orWhere('sku', 'like', '%' . $search . '%');
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
