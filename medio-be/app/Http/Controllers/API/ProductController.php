<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
}
