<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * GET /api/cart
     * Ambil cart aktif user beserta items dan produk.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = Cart::activeForUser($request->user()->id);
        $cart->load([
            'items.product.productImages',
            'items.lensOption',
            'items.lensCoating',
            'items.prescriptionProfile',
        ]);

        return response()->json($this->formatCart($cart));
    }

    /**
     * POST /api/cart/items
     * Tambah atau update item di cart.
     *
     * NOTE (P0-1): operasi cek stok + write cart_items dibungkus dalam
     * DB::transaction + lockForUpdate untuk mencegah race condition pada
     * stok produk saat order placement berjalan paralel.
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'              => 'required|exists:products,id',
            'quantity'                => 'required|integer|min:1|max:99',
            'variant'                 => 'nullable|array',
            'prescription'            => 'nullable|array',
            'lens_option_id'          => 'nullable|exists:lens_options,id',
            'lens_coating_id'         => 'nullable|exists:lens_coatings,id',
            'prescription_profile_id' => 'nullable|exists:prescription_profiles,id',
            'configuration_snapshot'  => 'nullable|array',
        ]);

        $cart = DB::transaction(function () use ($request) {
            // Lock baris produk agar pembacaan stok konsisten dengan transaksi
            // lain yang sedang decrement stok (order placement).
            $product = Product::where('id', $request->product_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock < $request->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stok produk tidak mencukupi.'],
                ]);
            }

            $cart = Cart::activeForUser($request->user()->id);
            $cart->update(['last_activity_at' => now()]);

            // Cek apakah item dengan konfigurasi sama sudah ada
            $existing = $cart->items()
                ->where('product_id', $request->product_id)
                ->where('lens_option_id', $request->lens_option_id)
                ->where('lens_coating_id', $request->lens_coating_id)
                ->first();

            if ($existing && ! $request->has('prescription') && ! $request->has('prescription_profile_id')) {
                $newQty = $existing->quantity + $request->quantity;
                if ($newQty > $product->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Total kuantitas melebihi stok yang tersedia.'],
                    ]);
                }
                $existing->update(['quantity' => $newQty]);
            } else {
                $cart->items()->create([
                    'product_id'              => $request->product_id,
                    'quantity'                => $request->quantity,
                    'variant'                 => $request->variant,
                    'prescription'            => $request->prescription,
                    'lens_option_id'          => $request->lens_option_id,
                    'lens_coating_id'         => $request->lens_coating_id,
                    'prescription_profile_id' => $request->prescription_profile_id,
                    'configuration_snapshot'  => $request->configuration_snapshot,
                ]);
            }

            return $cart;
        });

        $cart->load([
            'items.product.productImages',
            'items.lensOption',
            'items.lensCoating',
        ]);

        return response()->json($this->formatCart($cart), 201);
    }

    /**
     * PUT /api/cart/items/{itemId}
     * Update kuantitas item.
     *
     * NOTE (P0-1): pembacaan stok di-lock agar tidak race dengan
     * decrement stok di order placement.
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $cart = DB::transaction(function () use ($request, $itemId) {
            $cart = Cart::activeForUser($request->user()->id);
            $item = $cart->items()->findOrFail($itemId);

            $product = Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock < $request->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stok produk tidak mencukupi.'],
                ]);
            }

            $item->update(['quantity' => $request->quantity]);
            $cart->update(['last_activity_at' => now()]);

            return $cart;
        });

        $cart->load(['items.product.productImages', 'items.lensOption', 'items.lensCoating']);

        return response()->json($this->formatCart($cart));
    }

    /**
     * DELETE /api/cart/items/{itemId}
     * Hapus item dari cart.
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = Cart::activeForUser($request->user()->id);
        $item = $cart->items()->findOrFail($itemId);
        $item->delete();

        $cart->update(['last_activity_at' => now()]);
        $cart->load(['items.product.productImages', 'items.lensOption', 'items.lensCoating']);

        return response()->json($this->formatCart($cart));
    }

    /**
     * DELETE /api/cart
     * Kosongkan cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = Cart::activeForUser($request->user()->id);
        $cart->items()->delete();
        $cart->update(['last_activity_at' => now()]);

        return response()->json(['message' => 'Keranjang berhasil dikosongkan.']);
    }

    /**
     * POST /api/cart/sync
     * Sync cart dari localStorage ke server (dipanggil setelah login).
     * Payload: { items: [{ product_id, quantity, variant, ... }] }
     *
     * NOTE (P0-1): dibungkus transaction + lockForUpdate per produk untuk
     * konsistensi dengan operasi order yang berjalan paralel.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'items'                          => 'required|array',
            'items.*.product_id'             => 'required|exists:products,id',
            'items.*.quantity'               => 'required|integer|min:1',
            'items.*.variant'                => 'nullable|array',
            'items.*.prescription'           => 'nullable|array',
            'items.*.lens_option_id'         => 'nullable|exists:lens_options,id',
            'items.*.lens_coating_id'        => 'nullable|exists:lens_coatings,id',
            'items.*.prescription_profile_id'=> 'nullable|exists:prescription_profiles,id',
            'items.*.configuration_snapshot' => 'nullable|array',
        ]);

        $cart = DB::transaction(function () use ($request) {
            $cart = Cart::activeForUser($request->user()->id);

            foreach ($request->items as $itemData) {
                $product = Product::where('id', $itemData['product_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    continue; // skip produk tidak aktif
                }

                $qty = min((int) $itemData['quantity'], $product->stock);
                if ($qty < 1) {
                    continue;
                }

                $existing = $cart->items()
                    ->where('product_id', $itemData['product_id'])
                    ->where('lens_option_id', $itemData['lens_option_id'] ?? null)
                    ->where('lens_coating_id', $itemData['lens_coating_id'] ?? null)
                    ->first();

                if ($existing) {
                    $newQty = min($existing->quantity + $qty, $product->stock);
                    $existing->update(['quantity' => $newQty]);
                } else {
                    $cart->items()->create([
                        'product_id'              => $itemData['product_id'],
                        'quantity'                => $qty,
                        'variant'                 => $itemData['variant'] ?? null,
                        'prescription'            => $itemData['prescription'] ?? null,
                        'lens_option_id'          => $itemData['lens_option_id'] ?? null,
                        'lens_coating_id'         => $itemData['lens_coating_id'] ?? null,
                        'prescription_profile_id' => $itemData['prescription_profile_id'] ?? null,
                        'configuration_snapshot'  => $itemData['configuration_snapshot'] ?? null,
                    ]);
                }
            }

            $cart->update(['last_activity_at' => now()]);

            return $cart;
        });

        $cart->load(['items.product.productImages', 'items.lensOption', 'items.lensCoating']);

        return response()->json($this->formatCart($cart));
    }

    /**
     * Format cart response yang konsisten.
     */
    private function formatCart(Cart $cart): array
    {
        return [
            'id'     => $cart->id,
            'status' => $cart->status,
            'items'  => $cart->items->map(function ($item) {
                $product = $item->product;
                return [
                    'id'                      => $item->id,
                    'product_id'              => $item->product_id,
                    'name'                    => $product?->name,
                    'slug'                    => $product?->slug,
                    'price'                   => (float) ($product?->price ?? 0),
                    'stock'                   => $product?->stock ?? 0,
                    'image_url'               => $product?->productImages?->first()?->image_url,
                    'quantity'                => $item->quantity,
                    'variant'                 => $item->variant,
                    'prescription'            => $item->prescription,
                    'lens_option_id'          => $item->lens_option_id,
                    'lens_coating_id'         => $item->lens_coating_id,
                    'prescription_profile_id' => $item->prescription_profile_id,
                    'configuration_snapshot'  => $item->configuration_snapshot,
                    'lens_option'             => $item->lensOption ? [
                        'id'         => $item->lensOption->id,
                        'name'       => $item->lensOption->name,
                        'base_price' => (float) $item->lensOption->base_price,
                    ] : null,
                    'lens_coating'            => $item->lensCoating ? [
                        'id'    => $item->lensCoating->id,
                        'name'  => $item->lensCoating->name,
                        'price' => (float) $item->lensCoating->price,
                    ] : null,
                ];
            })->values(),
            'item_count'       => $cart->items->sum('quantity'),
            'last_activity_at' => $cart->last_activity_at?->toISOString(),
        ];
    }
}
