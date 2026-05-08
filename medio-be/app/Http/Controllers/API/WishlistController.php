<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Tampilkan semua wishlist user
     */
    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::with(['product.category'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($wishlists);
    }

    /**
     * Tambah produk ke wishlist
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Cek apakah sudah ada di wishlist
        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message'    => 'Produk sudah ada di wishlist.',
                'in_wishlist' => true,
            ], 422);
        }

        $wishlist = Wishlist::create([
            'user_id'    => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'message'    => 'Produk berhasil ditambahkan ke wishlist.',
            'wishlist'   => $wishlist->load('product'),
            'in_wishlist' => true,
        ], 201);
    }

    /**
     * Toggle wishlist (tambah jika belum ada, hapus jika sudah ada)
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $wishlist = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            return response()->json([
                'message'     => 'Produk dihapus dari wishlist.',
                'in_wishlist' => false,
            ]);
        }

        Wishlist::create([
            'user_id'    => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'message'     => 'Produk ditambahkan ke wishlist.',
            'in_wishlist' => true,
        ]);
    }

    /**
     * Hapus produk dari wishlist
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Produk tidak ada di wishlist.'], 404);
        }

        return response()->json(['message' => 'Produk berhasil dihapus dari wishlist.']);
    }

    /**
     * Cek status wishlist untuk produk tertentu
     */
    public function check(Request $request, int $productId): JsonResponse
    {
        $inWishlist = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        return response()->json(['in_wishlist' => $inWishlist]);
    }
}
