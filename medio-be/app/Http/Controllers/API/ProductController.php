<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $productRepo) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productRepo->getAll($request->all());

        return response()->json($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productRepo->findBySlug($slug);

        return response()->json($product);
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            return response()->json([
                'products' => [],
                'categories' => [],
            ]);
        }

        $like = '%' . $this->escapeLike($query) . '%';

        $products = Product::with(['category', 'activeProductImages'])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function ($productQuery) use ($like) {
                $productQuery
                    ->where('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('tags', 'like', $like)
                    ->orWhere('campaign_tags', 'like', $like)
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $like));
            })
            ->orderByDesc('recommendation_priority')
            ->orderByDesc('is_best_seller')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:2', 'max:4'],
            'product_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = collect($validated['product_ids'])->map(fn ($id) => (int) $id)->values();

        $products = Product::with(['category', 'activeProductImages'])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->values();

        if ($products->count() !== $ids->count()) {
            return response()->json([
                'message' => 'Produk compare tidak valid atau tidak tersedia.',
            ], 422);
        }

        return response()->json([
            'products' => $products,
            'attributes' => [
                'brand',
                'price',
                'stock',
                'gender',
                'frame_shape',
                'frame_material',
                'frame_color',
                'face_size_fit',
                'lens_width',
                'bridge_width',
                'temple_length',
                'frame_width',
                'is_prescription_required',
                'avg_rating',
                'review_count',
            ],
        ]);
    }

    public function recommendations(string $slug): JsonResponse
    {
        $product = Product::with('category')
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $categoryContext = strtolower(trim(($product->category?->slug ?? '') . ' ' . ($product->category?->name ?? '')));
        $isFrameProduct = str_contains($categoryContext, 'frame')
            || filled($product->frame_shape)
            || filled($product->frame_material)
            || filled($product->frame_color)
            || filled($product->face_size_fit)
            || filled($product->lens_width)
            || filled($product->bridge_width)
            || filled($product->temple_length)
            || filled($product->frame_width);

        $product->load([
            'compatibleLensOptions' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('name'),
        ]);

        $candidateQuery = Product::with([
                'category',
                'activeProductVariants',
                'activeProductImages',
            ])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where('id', '!=', $product->id);

        $similarCandidates = (clone $candidateQuery)
            ->where(function ($query) use ($product) {
                $matched = false;

                foreach (['frame_shape', 'frame_material', 'frame_color', 'gender', 'face_size_fit'] as $attribute) {
                    if (!empty($product->{$attribute})) {
                        $query->orWhere($attribute, $product->{$attribute});
                        $matched = true;
                    }
                }

                if ($product->category_id) {
                    $query->orWhere('category_id', $product->category_id);
                    $matched = true;
                }

                if (!$matched && $product->brand) {
                    $query->orWhere('brand', $product->brand);
                }
            })
            ->limit(60)
            ->get();

        $score = function (Product $candidate) use ($product): int {
            $weights = [
                'frame_shape' => 4,
                'frame_material' => 3,
                'face_size_fit' => 2,
                'gender' => 2,
                'frame_color' => 1,
                'brand' => 1,
            ];

            $score = $candidate->category_id === $product->category_id ? 2 : 0;
            $score += min(5, (int) floor(((int) $candidate->recommendation_priority) / 100));
            $score += $candidate->is_featured ? 2 : 0;

            foreach ($weights as $attribute => $weight) {
                if (!empty($product->{$attribute}) && $candidate->{$attribute} === $product->{$attribute}) {
                    $score += $weight;
                }
            }

            return $score;
        };

        $relatedProducts = (clone $candidateQuery)
            ->where('category_id', $product->category_id)
            ->orderByDesc('recommendation_priority')
            ->orderByDesc('is_best_seller')
            ->orderByDesc('is_new')
            ->limit(8)
            ->get();

        $similarFrames = $isFrameProduct
            ? $similarCandidates
                ->sortByDesc($score)
                ->take(8)
                ->values()
            : collect();

        $compatibleLenses = $isFrameProduct
            ? (clone $candidateQuery)
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery
                    ->where('slug', 'like', '%lensa%')
                    ->orWhere('name', 'like', '%lensa%'))
                ->limit(8)
                ->get()
            : collect();

        $frequentlyBoughtIds = OrderItem::query()
            ->selectRaw('product_id, SUM(quantity) as bought_count')
            ->where('product_id', '!=', $product->id)
            ->whereIn('order_id', fn ($query) => $query
                ->select('order_id')
                ->from('order_items')
                ->where('product_id', $product->id))
            ->whereHas('order', fn ($orderQuery) => $orderQuery
                ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered']))
            ->groupBy('product_id')
            ->orderByDesc('bought_count')
            ->limit(8)
            ->pluck('product_id');

        $frequentlyBoughtTogether = $frequentlyBoughtIds->isEmpty()
            ? collect()
            : (clone $candidateQuery)
                ->whereIn('id', $frequentlyBoughtIds)
                ->get()
                ->sortBy(fn (Product $candidate) => $frequentlyBoughtIds->search($candidate->id))
                ->values();

        return response()->json([
            'similar_frames' => $similarFrames,
            'compatible_lenses' => $compatibleLenses,
            'compatible_lens_options' => $product->compatibleLensOptions->values(),
            'frequently_bought_together' => $frequentlyBoughtTogether,
            'related_products' => $relatedProducts,
        ]);
    }

    public function brands(): JsonResponse
    {
        $brands = Product::select('brand')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return response()->json($brands);
    }

    public function filters(): JsonResponse
    {
        $activeProducts = Product::query()->where('is_active', true);

        $brands = (clone $activeProducts)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values();

        $priceRange = [
            'min' => (float) ((clone $activeProducts)->min('price') ?? 0),
            'max' => (float) ((clone $activeProducts)->max('price') ?? 0),
        ];

        $distinct = fn (string $column) => (clone $activeProducts)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();

        return response()->json([
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'brands' => $brands,
            'genders' => $distinct('gender'),
            'frame_shapes' => $distinct('frame_shape'),
            'frame_materials' => $distinct('frame_material'),
            'frame_colors' => $distinct('frame_color'),
            'face_size_fits' => $distinct('face_size_fit'),
            'price_range' => $priceRange,
            'flags' => [
                'in_stock_only' => true,
                'promo_only' => true,
                'prescription_supported' => (clone $activeProducts)
                    ->where('is_prescription_required', true)
                    ->exists(),
                'new_arrivals' => (clone $activeProducts)
                    ->where('is_new', true)
                    ->exists(),
                'featured' => (clone $activeProducts)
                    ->where('is_featured', true)
                    ->exists(),
            ],
        ]);
    }

    /**
     * Ambil semua review untuk produk tertentu, beserta avg rating
     */
    public function reviews(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'user_name'  => $r->user->name ?? 'Anonim',
                'created_at' => $r->created_at->diffForHumans(),
            ]);

        $avgRating = $reviews->avg('rating') ?? 0;

        return response()->json([
            'avg_rating'    => round($avgRating, 1),
            'total_reviews' => $reviews->count(),
            'reviews'       => $reviews,
        ]);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, "\\%_");
    }
}
