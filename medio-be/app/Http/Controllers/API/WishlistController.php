<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class WishlistController extends Controller
{
    /**
     * Tampilkan semua wishlist user
     */
    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::with([
                'product' => function ($query) {
                    $query->with('category')
                          ->withCount(['approvedReviews as review_count'])
                          ->withAvg(['approvedReviews as avg_rating'], 'rating');
                },
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($wishlists);
    }

    public function createShareLink(Request $request): JsonResponse
    {
        $productIds = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->pluck('product_id')
            ->values();

        $shareableIds = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->pluck('id')
            ->values();

        if ($shareableIds->isEmpty()) {
            return response()->json([
                'message' => 'Wishlist belum memiliki produk aktif yang bisa dibagikan.',
            ], 422);
        }

        $encryptedPayload = Crypt::encryptString(json_encode([
            'product_ids' => $shareableIds,
            'created_at' => now()->toIso8601String(),
        ]));

        return response()->json([
            'token' => rtrim(strtr(base64_encode($encryptedPayload), '+/', '-_'), '='),
        ]);
    }

    public function shared(string $token): JsonResponse
    {
        try {
            $base64 = strtr($token, '-_', '+/');
            $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
            $encryptedPayload = base64_decode($base64, true);
            if ($encryptedPayload === false) {
                throw new \RuntimeException('Invalid token encoding.');
            }

            $payload = json_decode(Crypt::decryptString($encryptedPayload), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Link wishlist tidak valid.',
            ], 404);
        }

        $ids = collect($payload['product_ids'] ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(40)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'products' => [],
            ]);
        }

        $products = Product::with(['category', 'activeProductImages'])
            ->withCount(['approvedReviews as review_count'])
            ->withAvg(['approvedReviews as avg_rating'], 'rating')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->values();

        return response()->json([
            'products' => $products,
        ]);
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
