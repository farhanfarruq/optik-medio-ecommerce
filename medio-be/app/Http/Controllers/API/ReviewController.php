<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * GET /api/products/{slug}/reviews
     * Ambil review yang sudah diapprove untuk produk.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    /**
     * POST /api/reviews
     * Submit review dengan opsional foto (maks 3 foto, maks 2MB per foto).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_item_id'  => 'required|exists:order_items,id',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'nullable|string|max:1000',
            'images'         => 'nullable|array|max:3',
            'images.*'       => 'file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $userId = $request->user()->id;

        $orderItem = OrderItem::with('order')->findOrFail($request->order_item_id);

        if ($orderItem->order->user_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array(strtolower($orderItem->order->status), ['delivered', 'completed'])) {
            return response()->json([
                'message' => 'Anda hanya bisa memberikan ulasan setelah pesanan diterima.',
            ], 422);
        }

        $existing = ProductReview::where('user_id', $userId)
            ->where('order_item_id', $request->order_item_id)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memberikan ulasan untuk produk ini.'], 422);
        }

        // Upload foto review
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('review-images', 'public');
            }
        }

        $review = ProductReview::create([
            'user_id'       => $userId,
            'product_id'    => $orderItem->product_id,
            'order_item_id' => $request->order_item_id,
            'rating'        => $request->rating,
            'comment'       => $request->comment ?? null,
            'images'        => $imagePaths ?: null,
        ]);

        return response()->json([
            'message' => 'Ulasan berhasil dikirim. Terima kasih!',
            'review'  => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * DELETE /api/reviews/{id}
     * Hapus review milik sendiri.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = ProductReview::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Hapus file foto jika ada
        if ($review->images) {
            foreach ($review->images as $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
        }

        $review->delete();

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
