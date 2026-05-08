<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Submit review untuk produk (hanya untuk item yang sudah delivered)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_item_id' => 'required|exists:order_items,id',
            'rating'        => 'required|integer|min:1|max:5',
            'comment'       => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()->id;

        // Verifikasi: order item harus milik user ini dan order-nya delivered
        $orderItem = OrderItem::with('order')->findOrFail($validated['order_item_id']);

        if ($orderItem->order->user_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (strtolower($orderItem->order->status) !== 'delivered') {
            return response()->json([
                'message' => 'Anda hanya bisa memberikan ulasan setelah pesanan diterima.',
            ], 422);
        }

        // Cek apakah sudah pernah review item ini
        $existing = ProductReview::where('user_id', $userId)
            ->where('order_item_id', $validated['order_item_id'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memberikan ulasan untuk produk ini.'], 422);
        }

        $review = ProductReview::create([
            'user_id'       => $userId,
            'product_id'    => $orderItem->product_id,
            'order_item_id' => $validated['order_item_id'],
            'rating'        => $validated['rating'],
            'comment'       => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Ulasan berhasil dikirim. Terima kasih!',
            'review'  => $review,
        ], 201);
    }

    /**
     * Hapus review milik sendiri
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = ProductReview::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $review->delete();

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
