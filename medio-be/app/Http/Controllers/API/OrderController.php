<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\LoyaltyPointLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Discount;
use App\Models\Promo;
use App\Models\ShippingAddress;
use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\PrescriptionProfile;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\OpticalPricingService;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\XenditService;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private RajaOngkirService $shippingService,
        private OpticalPricingService $opticalPricingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderRepo->getUserOrders($request->user()->id);
        return response()->json($orders);
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'items.*.variant'              => 'nullable|array',
            'items.*.prescription'         => 'nullable|array',
            'items.*.linked_item_index'    => 'nullable|integer',
            'items.*.lens_option_id'       => 'nullable|exists:lens_options,id',
            'items.*.lens_coating_id'      => 'nullable|exists:lens_coatings,id',
            'items.*.prescription_profile_id' => 'nullable|exists:prescription_profiles,id',
            'discount_id'                  => 'nullable|exists:discounts,id',
            'promo_id'                     => 'nullable|exists:promos,id',
            'shipping_address_id'          => 'nullable|exists:shipping_addresses,id',
            'shipping_rate_id'             => 'nullable|exists:shipping_rates,id',
            'shipping_cost'                => 'nullable|numeric|min:0',
            'courier'                      => 'nullable|string',
            'courier_service'              => 'nullable|string',
            'payment_method_id'            => 'nullable|exists:payment_methods,id',
            'bank_id'                      => 'nullable|exists:banks,id',
            'loyalty_points_used'          => 'nullable|integer|min:0',
        ]);

        if ($request->discount_id && $request->promo_id) {
            return response()->json(['message' => 'Hanya bisa menggunakan satu jenis potongan (Promo atau Diskon).'], 422);
        }

        if ($request->filled('shipping_address_id')) {
            $ownsAddress = ShippingAddress::query()
                ->whereKey($request->shipping_address_id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$ownsAddress) {
                return response()->json([
                    'message' => 'Alamat pengiriman tidak ditemukan atau bukan milik Anda.',
                ], 403);
            }
        }

        $items = [];
        $subtotal = 0;
        $productQty = [];
        $cartProducts = [];

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $isLinkedLens = isset($item['linked_item_index']);

            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'message' => 'Stok produk "' . $product->name . '" tidak mencukupi.',
                ], 422);
            }

            if ($product->is_prescription_required && empty($item['prescription']) && empty($item['prescription_profile_id']) && !$isLinkedLens) {
                return response()->json([
                    'message' => 'Produk "' . $product->name . '" membutuhkan data resep mata.',
                ], 422);
            }

            $opticalConfiguration = $this->resolveOpticalConfiguration($request, $product, $item);
            if (!$opticalConfiguration['compatible']) {
                return response()->json([
                    'message' => $opticalConfiguration['warnings'][0] ?? 'Konfigurasi optik tidak kompatibel.',
                ], 422);
            }

            $unitPrice = $product->price + $opticalConfiguration['price_breakdown']['lens_price'] + $opticalConfiguration['price_breakdown']['coating_price'];

            $cartProducts[] = $product;
            $subtotal += $unitPrice * $item['quantity'];
            $productQty[$product->id] = ($productQty[$product->id] ?? 0) + $item['quantity'];
            
            $items[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'name'          => $product->name,
                'product_price' => $unitPrice,
                'price'         => $unitPrice,
                'quantity'      => $item['quantity'],
                'is_free'       => false,
                'variant'       => $item['variant'] ?? null,
                'image'         => $product->primaryImagePath(),
                'lens_price'    => $opticalConfiguration['price_breakdown']['lens_price'],
                'coating_price' => $opticalConfiguration['price_breakdown']['coating_price'],
                'configuration_snapshot' => $opticalConfiguration['configuration_snapshot'],
            ];
        }

        $discountAmount = 0;
        $promoDiscountAmount = 0;

        if ($request->discount_id) {
            $discount = Discount::find($request->discount_id);
            if ($discount && $discount->isValid()) {
                $alreadyUsed = \App\Models\DiscountUsage::where('user_id', $request->user()->id)
                    ->where('discount_id', $discount->id)
                    ->exists();

                if ($alreadyUsed) {
                    return response()->json([
                        'message' => 'Kode diskon ini sudah pernah Anda gunakan.',
                    ], 422);
                }

                if ($discount->type === 'percentage') {
                    $discountAmount = ($subtotal * $discount->value) / 100;
                } else {
                    $discountAmount = $discount->value;
                }
                $discountAmount = min($discountAmount, $subtotal);
            }
        }

        $promoId = $request->promo_id;
        $appliedPromo = null;

        // Auto-detect Buy X Get Y if no promo is explicitly selected AND no manual discount is applied
        if (!$request->has('promo_id') && $discountAmount == 0) {
            $applicablePromo = Promo::where('is_active', true)
                ->where('type', 'buy_x_get_y')
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->get()
                ->first(function($p) use ($productQty, $cartProducts) {
                    $qty = $this->getPromoBuyQty($p, $productQty, $cartProducts);
                    return $qty >= $p->buy_quantity;
                });
            
            if ($applicablePromo) {
                $promoId = $applicablePromo->id;
            }
        }

        // Exclusivity: if discount is applied, promo cannot be used
        if ($discountAmount > 0) {
            $promoId = null;
        }

        if ($promoId) {
            $promo = Promo::with(['buyProducts', 'discountProducts', 'getProduct', 'discountProduct'])
                ->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->find($promoId);

            if ($promo) {
                // Check usage limit per user
                if ($promo->usage_limit_per_user) {
                    $usageCount = \App\Models\PromoUsage::where('user_id', $request->user()->id)
                        ->where('promo_id', $promo->id)
                        ->count();
                    
                    if ($usageCount >= $promo->usage_limit_per_user) {
                        if ($request->has('promo_id')) {
                            return response()->json(['message' => 'Anda telah mencapai batas pemakaian untuk promo ini.'], 422);
                        }
                        $promo = null;
                        $promoId = null;
                    }
                }
                $appliedPromo = $promo;
            }

            if ($promo) {
                if ($promo->type === 'buy_x_get_y') {
                    $qty = $this->getPromoBuyQty($promo, $productQty, $cartProducts);
                    if ($qty >= $promo->buy_quantity) {
                        $multiplier = floor($qty / $promo->buy_quantity);
                        $freeQty = $multiplier * $promo->get_quantity;
                        $getProd = $promo->getProduct;
                        if ($getProd) {
                            $items[] = [
                                'product_id'    => $getProd->id,
                                'product_name'  => $getProd->name . ' (Free)',
                                'name'          => $getProd->name . ' (Free)',
                                'product_price' => 0,
                                'price'         => 0,
                                'quantity'      => $freeQty,
                                'is_free'       => true,
                                'image'         => $getProd->primaryImagePath(),
                                'variant'       => null,
                            ];
                        }
                    }
                } elseif ($promo->type === 'transaction_discount') {
                    if ($subtotal >= $promo->min_transaction_amount) {
                        if ($promo->discount_type === 'percentage') {
                            $promoDiscountAmount = ($subtotal * $promo->discount_value) / 100;
                        } else {
                            $promoDiscountAmount = $promo->discount_value;
                        }
                        $promoDiscountAmount = min($promoDiscountAmount, $subtotal);
                    }
                } elseif ($promo->type === 'product_discount') {
                    $discBase = $this->getPromoDiscountBase($promo, $productQty, $cartProducts);
                    if ($discBase['qty'] > 0) {
                        if ($promo->discount_type === 'percentage') {
                            $promoDiscountAmount = ($discBase['total_price'] * $promo->discount_value) / 100;
                        } else {
                            $promoDiscountAmount = $promo->discount_value * $discBase['qty'];
                        }
                        $promoDiscountAmount = min($promoDiscountAmount, $subtotal);
                    }
                }
            }
        }

        $paymentMethod = $this->resolvePaymentMethod($request);
        $bank = $this->resolveBank($request, $paymentMethod);
        $shippingSelection = $this->resolveShippingSelection($request);
        $shipping = $shippingSelection['shipping_cost'];

        // ── Level Member Discount ──────────────────────────────────────────────
        $levelDiscountAmount = 0;
        $levelMembership = $request->user()
            ->levelMemberships()
            ->with('levelMember')
            ->whereNull('effective_until')
            ->latest()
            ->first();
        $levelMember = $levelMembership?->levelMember;
        if ($levelMember && $levelMember->discount_percentage > 0) {
            $levelDiscountAmount = round(($subtotal * $levelMember->discount_percentage) / 100, 2);
            $levelDiscountAmount = min($levelDiscountAmount, $subtotal);
        }

        // ── Loyalty Points Redemption ──────────────────────────────────────────
        // 1 poin = Rp 1.000. User bisa redeem maks 5% dari subtotal.
        $loyaltyPointsToUse = max(0, (int) $request->input('loyalty_points_used', 0));
        $loyaltyDiscountAmount = 0;
        if ($loyaltyPointsToUse > 0) {
            $userPoints = $request->user()->loyalty_points;
            $loyaltyPointsToUse = min($loyaltyPointsToUse, $userPoints);
            $maxLoyaltyDiscount = (int) floor($subtotal * 0.05); // maks 5% subtotal
            $loyaltyDiscountAmount = min($loyaltyPointsToUse * 1000, $maxLoyaltyDiscount);
            $loyaltyPointsToUse = (int) ceil($loyaltyDiscountAmount / 1000);
        }

        $totalPrice = max(0, $subtotal + $shipping - $discountAmount - $promoDiscountAmount - $levelDiscountAmount - $loyaltyDiscountAmount);

        // Add individual item discount info for the UI
        if ($appliedPromo && $appliedPromo->type === 'product_discount') {
            $multiDiscIds = $appliedPromo->discountProducts->pluck('id')->toArray();
            foreach ($items as &$item) {
                $isMatch = ($item['product_id'] == $appliedPromo->discount_product_id) || 
                           in_array($item['product_id'], $multiDiscIds);
                
                if (!$isMatch && $appliedPromo->discount_brands) {
                    $p = collect($cartProducts)->firstWhere('id', $item['product_id']);
                    if ($p && in_array($p->brand, $appliedPromo->discount_brands)) {
                        $isMatch = true;
                    }
                }

                if ($isMatch) {
                    $item['is_discounted'] = true;
                    if ($appliedPromo->discount_type === 'percentage') {
                        $item['discounted_price'] = $item['price'] * (1 - ($appliedPromo->discount_value / 100));
                    } else {
                        // For flat discount, spread it or show as is
                        $item['discounted_price'] = max(0, $item['price'] - ($appliedPromo->discount_value / ($productQty[$item['product_id']] ?: 1)));
                    }
                }
            }
        }

        return response()->json([
            'subtotal'                => $subtotal,
            'shipping_cost'           => $shipping,
            'discount_amount'         => $discountAmount,
            'promo_discount_amount'   => $promoDiscountAmount,
            'level_discount_amount'   => $levelDiscountAmount,
            'level_member'            => $levelMember ? ['name' => $levelMember->name, 'discount_percentage' => $levelMember->discount_percentage] : null,
            'loyalty_points_balance'  => $request->user()->loyalty_points,
            'loyalty_points_used'     => $loyaltyPointsToUse,
            'loyalty_discount_amount' => $loyaltyDiscountAmount,
            'total_price'             => $totalPrice,
            'items'                   => $items,
            'applied_promo'           => $appliedPromo,
            'payment_method'          => $paymentMethod,
            'selected_bank'           => $bank,
            'shipping_selection'      => $shippingSelection,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_address_id'          => 'required|exists:shipping_addresses,id',
            'shipping_rate_id'             => 'nullable|exists:shipping_rates,id',
            'courier'                      => 'nullable|string',
            'courier_service'              => 'nullable|string',
            'shipping_cost'                => 'nullable|numeric|min:0',
            'payment_method_id'            => 'required|exists:payment_methods,id',
            'bank_id'                      => 'nullable|exists:banks,id',
            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'items.*.variant'              => 'nullable|array',
            'items.*.prescription'         => 'nullable|array',
            'items.*.linked_item_index'    => 'nullable|integer',
            'items.*.lens_option_id'       => 'nullable|exists:lens_options,id',
            'items.*.lens_coating_id'      => 'nullable|exists:lens_coatings,id',
            'items.*.prescription_profile_id' => 'nullable|exists:prescription_profiles,id',
            'notes'                        => 'nullable|string|max:500',
            'discount_id'                  => 'nullable|exists:discounts,id',
            'promo_id'                     => 'nullable|exists:promos,id',
            'loyalty_points_used'          => 'nullable|integer|min:0',
        ]);

        if ($request->discount_id && $request->promo_id) {
            return response()->json(['message' => 'Hanya bisa menggunakan satu jenis potongan (Promo atau Diskon).'], 422);
        }

        // ── CHKOUT-003: Validasi kepemilikan shipping address ─────────────────
        $shippingAddress = \App\Models\ShippingAddress::where('id', $request->shipping_address_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$shippingAddress) {
            return response()->json([
                'message' => 'Alamat pengiriman tidak ditemukan atau bukan milik Anda.',
            ], 403);
        }

        $paymentMethod = $this->resolvePaymentMethod($request, true);
        $bank = $this->resolveBank($request, $paymentMethod);
        $shippingSelection = $this->resolveShippingSelection($request, true);

        $items    = [];
        $subtotal = 0;
        $productQty = [];
        $cartProducts = [];

        foreach ($request->items as $item) {
            $product      = Product::findOrFail($item['product_id']);
            $cartProducts[] = $product;
            $isLinkedLens = isset($item['linked_item_index']);

            if (!$isLinkedLens && $product->stock < $item['quantity']) {
                return response()->json([
                    'message' => 'Stok produk "' . $product->name . '" tidak mencukupi.',
                ], 422);
            }

            if ($product->is_prescription_required && empty($item['prescription']) && empty($item['prescription_profile_id']) && !$isLinkedLens) {
                return response()->json([
                    'message' => 'Produk "' . $product->name . '" membutuhkan data resep mata.',
                ], 422);
            }

            $opticalConfiguration = $this->resolveOpticalConfiguration($request, $product, $item);
            if (!$opticalConfiguration['compatible']) {
                return response()->json([
                    'message' => $opticalConfiguration['warnings'][0] ?? 'Konfigurasi optik tidak kompatibel.',
                ], 422);
            }

            $unitPrice = $product->price + $opticalConfiguration['price_breakdown']['lens_price'] + $opticalConfiguration['price_breakdown']['coating_price'];

            $subtotal += $unitPrice * $item['quantity'];
            $productQty[$product->id] = ($productQty[$product->id] ?? 0) + $item['quantity'];

            $items[] = [
                'product_id'        => $product->id,
                'product_name'      => $product->name,
                'product_price'     => $unitPrice,
                'quantity'          => $item['quantity'],
                'weight'            => $product->weight,
                'variant'           => $item['variant'] ?? null,
                'prescription'      => $item['prescription'] ?? null,
                'linked_item_index' => $item['linked_item_index'] ?? null,
                'lens_option_id'    => $item['lens_option_id'] ?? null,
                'lens_coating_id'   => $item['lens_coating_id'] ?? null,
                'prescription_profile_id' => $item['prescription_profile_id'] ?? null,
                'lens_price'        => $opticalConfiguration['price_breakdown']['lens_price'],
                'coating_price'     => $opticalConfiguration['price_breakdown']['coating_price'],
                'configuration_snapshot' => $opticalConfiguration['configuration_snapshot'],
            ];
        }

        $discountAmount = 0;
        $discountToRecord = null;
        if ($request->discount_id) {
            $discount = Discount::find($request->discount_id);
            if ($discount && $discount->isValid()) {
                // Validasi: satu user hanya boleh pakai kode ini 1x
                $alreadyUsed = \App\Models\DiscountUsage::where('user_id', $request->user()->id)
                    ->where('discount_id', $discount->id)
                    ->exists();

                if ($alreadyUsed) {
                    return response()->json([
                        'message' => 'Kode diskon ini sudah pernah Anda gunakan.',
                    ], 422);
                }

                if ($discount->type === 'percentage') {
                    $discountAmount = ($subtotal * $discount->value) / 100;
                } else {
                    $discountAmount = $discount->value;
                }
                $discountAmount = min($discountAmount, $subtotal);
                $discountToRecord = $discount;
            }
        }

        $promoDiscountAmount = 0;
        $promoId = $request->promo_id;

        // Auto-detect Buy X Get Y if no promo is selected AND no discount is applied
        if (!$request->has('promo_id') && $discountAmount == 0) {
            $applicablePromo = Promo::where('is_active', true)
                ->where('type', 'buy_x_get_y')
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->get()
                ->first(function($p) use ($productQty) {
                    return isset($productQty[$p->buy_product_id]) && $productQty[$p->buy_product_id] >= $p->buy_quantity;
                });
            
            if ($applicablePromo) {
                $promoId = $applicablePromo->id;
            }
        }

        // Exclusivity: if discount is applied, promo cannot be used
        if ($discountAmount > 0) {
            $promoId = null;
        }

        if ($promoId) {
            $promo = Promo::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->find($promoId);

            if ($promo) {
                if ($promo->usage_limit_per_user) {
                    $usedCount = \App\Models\PromoUsage::where('user_id', $request->user()->id)
                        ->where('promo_id', $promo->id)
                        ->count();
                    if ($usedCount >= $promo->usage_limit_per_user) {
                        return response()->json(['message' => 'Anda sudah mencapai batas penggunaan promo ini.'], 422);
                    }
                }
                if ($promo->type === 'buy_x_get_y') {
                    $qty = $this->getPromoBuyQty($promo, $productQty, $cartProducts);
                    if ($qty >= $promo->buy_quantity) {
                        $multiplier = floor($qty / $promo->buy_quantity);
                        $freeQty = $multiplier * $promo->get_quantity;
                        $getProd = $promo->getProduct;
                        if ($getProd) {
                            $items[] = [
                                'product_id'        => $getProd->id,
                                'product_name'      => $getProd->name . ' (Free)',
                                'product_price'     => 0,
                                'quantity'          => $freeQty,
                                'weight'            => $getProd->weight,
                                'variant'           => null,
                                'prescription'      => null,
                                'linked_item_index' => null,
                            ];
                        }
                    }
                } elseif ($promo->type === 'transaction_discount') {
                    if ($subtotal >= $promo->min_transaction_amount) {
                        if ($promo->discount_type === 'percentage') {
                            $promoDiscountAmount = ($subtotal * $promo->discount_value) / 100;
                        } else {
                            $promoDiscountAmount = $promo->discount_value;
                        }
                        $promoDiscountAmount = min($promoDiscountAmount, $subtotal);
                    }
                } elseif ($promo->type === 'product_discount') {
                    $discBase = $this->getPromoDiscountBase($promo, $productQty, $cartProducts);
                    if ($discBase['qty'] > 0) {
                        if ($promo->discount_type === 'percentage') {
                            $promoDiscountAmount = ($discBase['total_price'] * $promo->discount_value) / 100;
                        } else {
                            $promoDiscountAmount = $promo->discount_value * $discBase['qty'];
                        }
                        $promoDiscountAmount = min($promoDiscountAmount, $subtotal);
                    }
                }
            }
        }

        // ── Level Member Discount (store) ─────────────────────────────────────
        $levelDiscountAmount = 0;
        $storeLevelMembership = $request->user()
            ->levelMemberships()
            ->with('levelMember')
            ->whereNull('effective_until')
            ->latest()
            ->first();
        $storeLevelMember = $storeLevelMembership?->levelMember;
        if ($storeLevelMember && $storeLevelMember->discount_percentage > 0) {
            $levelDiscountAmount = round(($subtotal * $storeLevelMember->discount_percentage) / 100, 2);
            $levelDiscountAmount = min($levelDiscountAmount, $subtotal);
        }

        // ── Loyalty Points Redemption (store) ─────────────────────────────────
        $loyaltyPointsToUse = max(0, (int) $request->input('loyalty_points_used', 0));
        $loyaltyDiscountAmount = 0;
        if ($loyaltyPointsToUse > 0) {
            $userPoints = $request->user()->loyalty_points;
            $loyaltyPointsToUse = min($loyaltyPointsToUse, $userPoints);
            $maxLoyaltyDiscount = (int) floor($subtotal * 0.05); // maks 5% subtotal
            $loyaltyDiscountAmount = min($loyaltyPointsToUse * 1000, $maxLoyaltyDiscount);
            $loyaltyPointsToUse = (int) ceil($loyaltyDiscountAmount / 1000);
        }

        $orderData = [
            'user_id'                 => $request->user()->id,
            'shipping_address_id'     => $request->shipping_address_id,
            'status'                  => 'unpaid',
            'subtotal'                => $subtotal,
            'shipping_cost'           => $shippingSelection['shipping_cost'],
            'discount_id'             => $request->discount_id,
            'discount_amount'         => $discountAmount,
            'promo_id'                => $promoId,
            'promo_discount_amount'   => $promoDiscountAmount,
            'level_discount_amount'   => $levelDiscountAmount,
            'loyalty_points_used'     => $loyaltyPointsToUse,
            'loyalty_discount_amount' => $loyaltyDiscountAmount,
            'total_price'             => max(0, $subtotal + $shippingSelection['shipping_cost'] - $discountAmount - $promoDiscountAmount - $levelDiscountAmount - $loyaltyDiscountAmount),
            'courier'                 => $shippingSelection['courier'],
            'courier_service'         => $shippingSelection['courier_service'],
            'notes'                   => $request->notes,
            'bank_id'                 => $bank?->id,
            'payment_method_model'    => $paymentMethod,
        ];

        // ── SHIP-002: Validasi berat total item di backend ────────────────────
        $totalWeight = 0;
        foreach ($items as $item) {
            if (!isset($item['linked_item_index'])) {
                $totalWeight += ($item['weight'] ?? 0) * $item['quantity'];
            }
        }
        if ($totalWeight <= 0) {
            return response()->json([
                'message' => 'Total berat produk tidak valid. Pastikan semua produk memiliki data berat.',
            ], 422);
        }

        $order = DB::transaction(function () use ($request, $orderData, $items, $promoId, $loyaltyPointsToUse, $discountToRecord) {
            $order = $this->orderRepo->create($orderData, $items);

            if ($discountToRecord) {
                $discountToRecord->increment('used_count');

                \App\Models\DiscountUsage::create([
                    'user_id'     => $request->user()->id,
                    'discount_id' => $discountToRecord->id,
                    'order_id'    => $order->id,
                ]);
            }

            if ($promoId) {
                \App\Models\PromoUsage::create([
                    'user_id'  => $request->user()->id,
                    'promo_id' => $promoId,
                    'order_id' => $order->id,
                ]);
            }

            if ($loyaltyPointsToUse > 0) {
                $redeemed = $request->user()->redeemLoyaltyPoints(
                    $loyaltyPointsToUse,
                    $order->id,
                    "Poin digunakan untuk diskon pesanan #{$order->order_number}"
                );

                if (!$redeemed) {
                    throw ValidationException::withMessages([
                        'loyalty_points_used' => ['Saldo loyalty points tidak mencukupi.'],
                    ]);
                }
            }

            foreach ($request->items as $item) {
                $updated = Product::where('id', $item['product_id'])
                    ->where('stock', '>=', $item['quantity'])
                    ->decrement('stock', $item['quantity']);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Stok produk berubah saat checkout. Silakan periksa keranjang kembali.'],
                    ]);
                }
            }

            return $order->fresh(['items', 'payment.paymentMethod', 'shippingAddress', 'bank', 'logs.actedBy']);
        });

        // Catat business event order_created
        \App\Models\BusinessEvent::record(
            eventType: \App\Models\BusinessEvent::ORDER_CREATED,
            payload: [
                'order_id'    => $order->id,
                'order_number'=> $order->order_number,
                'total_price' => (float) $order->total_price,
                'item_count'  => $order->items->count(),
            ]
        );

        return response()->json($order, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order);
    }

    public function uploadPaymentProof(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ]);

        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$order->payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        if ($order->payment->provider === 'xendit') {
            return response()->json([
                'message' => 'Pesanan ini menggunakan pembayaran online dan tidak memerlukan upload bukti bayar manual.',
            ], 422);
        }

        if (in_array($order->status, ['cancelled', 'refunded', 'delivered'], true)) {
            return response()->json([
                'message' => 'Bukti pembayaran tidak dapat diunggah untuk pesanan dengan status ini.',
            ], 422);
        }

        if ($order->is_payment_verified) {
            return response()->json([
                'message' => 'Pembayaran pesanan ini sudah diverifikasi.',
            ], 422);
        }

        $paymentProofPath = $request->file('payment_proof')->store('payment-proofs', 'public');

        $order->update([
            'payment_proof_image' => $paymentProofPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diunggah.',
            'data' => $order->fresh(['payment.paymentMethod', 'bank']),
        ]);
    }

    public function tracking(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat tracking pesanan berhasil diambil.',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'tracking_number' => $order->tracking_number,
                'courier' => $order->courier,
                'courier_service' => $order->courier_service,
                'payment_proof_image' => $order->payment_proof_image,
                'is_payment_verified' => $order->is_payment_verified,
                'payment_verified_at' => optional($order->payment_verified_at)?->toISOString(),
                'logs' => $order->logs
                    ->values()
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'event_type' => $log->event_type,
                        'previous_status' => $log->previous_status,
                        'current_status' => $log->current_status,
                        'title' => $log->title,
                        'description' => $log->description,
                        'metadata' => $log->metadata,
                        'acted_by' => $log->actedBy ? [
                            'id' => $log->actedBy->id,
                            'name' => $log->actedBy->name,
                        ] : null,
                        'created_at' => $log->created_at?->toISOString(),
                    ]),
            ],
        ]);
    }

    public function syncPayment(Request $request, int $id, XenditService $xenditService): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$order->payment) {
            return response()->json(['message' => 'Payment record not found'], 404);
        }

        if ($order->payment->provider !== 'xendit') {
            return response()->json([
                'message' => 'Metode pembayaran ini tidak menggunakan sinkronisasi gateway.',
                'status' => $order->status,
                'order' => $order->fresh(['payment.paymentMethod', 'bank']),
            ], 422);
        }

        $status = $xenditService->syncInvoice($order);

        return response()->json([
            'message' => 'Sync completed',
            'status'  => $status,
            'order'   => $order->fresh(['payment.paymentMethod', 'bank', 'logs.actedBy'])
        ]);
    }

    /**
     * GET /api/orders/{id}/payment-status
     * Polling endpoint ringan untuk cek status pembayaran.
     * Digunakan oleh WaitingPayment page untuk auto-refresh.
     */
    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment = $order->payment;

        return response()->json([
            'order_id'            => $order->id,
            'order_number'        => $order->order_number,
            'order_status'        => $order->status,
            'is_payment_verified' => (bool) $order->is_payment_verified,
            'paid_at'             => optional($order->paid_at)?->toISOString(),
            'payment' => $payment ? [
                'provider'       => $payment->provider,
                'status'         => $payment->status,
                'payment_type'   => $payment->payment_type,
                'payment_method' => $payment->payment_method,
                'checkout_url'   => $payment->checkout_url,
                'paid_at'        => optional($payment->paid_at)?->toISOString(),
            ] : null,
            // Apakah perlu redirect ke halaman order detail
            'should_redirect' => in_array($order->status, ['paid', 'processing', 'shipped', 'delivered', 'completed']),
            // Apakah pembayaran sudah expired/cancelled
            'is_expired'      => in_array($order->status, ['cancelled']) &&
                                  in_array($payment?->status, ['expired', 'failed', 'cancelled']),
        ]);
    }

    public function confirmDelivery(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (strtolower($order->status) !== 'shipped') {
            return response()->json([
                'message' => 'Pesanan hanya bisa dikonfirmasi saat statusnya sudah dikirim.',
            ], 422);
        }

        // Poin yang didapat: 1 poin per Rp 10.000 dari total_price (dibulatkan ke bawah, min 1)
        $pointsToEarn = max(1, (int) floor($order->total_price / 10000));

        DB::transaction(function () use ($order, $pointsToEarn) {
            $updateData = [
                'status'                => 'delivered',
                'delivered_at'          => now(),
                'loyalty_points_earned' => $pointsToEarn,
            ];

            // COD: otomatis verifikasi pembayaran saat barang diterima
            $isCod = strtolower($order->payment?->paymentMethod?->code ?? '') === 'cod';
            if ($isCod && !$order->is_payment_verified) {
                $updateData['is_payment_verified']  = true;
                $updateData['payment_verified_at']  = now();
                $updateData['paid_at']              = $order->paid_at ?? now();

                if ($order->payment) {
                    $order->payment->update([
                        'status'  => 'success',
                        'paid_at' => now(),
                    ]);
                }
            }

            $order->update($updateData);

            $order->user->addLoyaltyPoints(
                $pointsToEarn,
                $order->id,
                "Poin dari pesanan #{$order->order_number}"
            );
        });

        return response()->json([
            'message'       => 'Pesanan berhasil dikonfirmasi diterima.',
            'points_earned' => $pointsToEarn,
            'total_points'  => $order->user->fresh()->loyalty_points,
            'order'         => $order->fresh(['items.product', 'payment.paymentMethod', 'shippingAddress', 'logs.actedBy']),
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepo->findById($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'unpaid') {
            return response()->json([
                'message' => 'Pesanan hanya bisa dibatalkan jika belum dibayar.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (!$item->parent_item_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'order'   => $order->fresh(['payment.paymentMethod', 'logs.actedBy']),
        ]);
    }

    public function loyaltyHistory(Request $request): JsonResponse
    {
        $logs = LoyaltyPointLog::where('user_id', $request->user()->id)
            ->with('order:id,order_number')
            ->latest()
            ->paginate(20);

        return response()->json([
            'total_points' => $request->user()->loyalty_points,
            'history'      => $logs,
        ]);
    }

    private function getPromoBuyQty($promo, $productQty, $cartProducts)
    {
        $totalQty = 0;
        $multiBuyIds = $promo->buyProducts->pluck('id')->toArray();
        $countedIds = [];

        // 1. Single product
        if ($promo->buy_product_id && isset($productQty[$promo->buy_product_id])) {
            $totalQty += $productQty[$promo->buy_product_id];
            $countedIds[] = $promo->buy_product_id;
        }

        // 2. Multiple products
        foreach ($multiBuyIds as $pid) {
            if (!in_array($pid, $countedIds) && isset($productQty[$pid])) {
                $totalQty += $productQty[$pid];
                $countedIds[] = $pid;
            }
        }

        // 3. Brands
        if ($promo->buy_brands && count($promo->buy_brands) > 0) {
            foreach ($cartProducts as $p) {
                if (!in_array($p->id, $countedIds) && in_array($p->brand, $promo->buy_brands)) {
                    $totalQty += ($productQty[$p->id] ?? 0);
                    $countedIds[] = $p->id;
                }
            }
        }

        return $totalQty;
    }

    private function getPromoDiscountBase($promo, $productQty, $cartProducts)
    {
        $qty = 0;
        $totalPrice = 0;
        $multiDiscIds = $promo->discountProducts->pluck('id')->toArray();
        $countedIds = [];

        // 1. Single product
        if ($promo->discount_product_id && isset($productQty[$promo->discount_product_id])) {
            $qty += $productQty[$promo->discount_product_id];
            $p = collect($cartProducts)->firstWhere('id', $promo->discount_product_id);
            $totalPrice += ($p ? $p->price * $productQty[$promo->discount_product_id] : 0);
            $countedIds[] = $promo->discount_product_id;
        }

        // 2. Multiple products
        foreach ($multiDiscIds as $pid) {
            if (!in_array($pid, $countedIds) && isset($productQty[$pid])) {
                $qty += $productQty[$pid];
                $p = collect($cartProducts)->firstWhere('id', $pid);
                $totalPrice += ($p ? $p->price * $productQty[$pid] : 0);
                $countedIds[] = $pid;
            }
        }

        // 3. Brands
        if ($promo->discount_brands && count($promo->discount_brands) > 0) {
            foreach ($cartProducts as $p) {
                if (!in_array($p->id, $countedIds) && in_array($p->brand, $promo->discount_brands)) {
                    $qty += ($productQty[$p->id] ?? 0);
                    $totalPrice += ($p->price * ($productQty[$p->id] ?? 0));
                    $countedIds[] = $p->id;
                }
            }
        }

        return ['qty' => $qty, 'total_price' => $totalPrice];
    }

    private function resolvePaymentMethod(Request $request, bool $strict = false): ?PaymentMethod
    {
        $paymentMethodId = $request->input('payment_method_id');

        if (!$paymentMethodId) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'payment_method_id' => ['Metode pembayaran wajib dipilih.'],
                ]);
            }

            return null;
        }

        $paymentMethod = PaymentMethod::query()
            ->whereKey($paymentMethodId)
            ->where('is_active', true)
            ->first();

        if (!$paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => ['Metode pembayaran tidak aktif atau tidak ditemukan.'],
            ]);
        }

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function resolveOpticalConfiguration(Request $request, Product $product, array $item): array
    {
        $lensOption = !empty($item['lens_option_id'])
            ? LensOption::where('is_active', true)->findOrFail($item['lens_option_id'])
            : null;
        $lensCoating = !empty($item['lens_coating_id'])
            ? LensCoating::where('is_active', true)->findOrFail($item['lens_coating_id'])
            : null;
        $profile = null;

        if (!empty($item['prescription_profile_id'])) {
            $profile = PrescriptionProfile::where('user_id', $request->user()->id)
                ->whereKey($item['prescription_profile_id'])
                ->firstOrFail();
        }

        return $this->opticalPricingService->configure(
            frame: $product,
            lensOption: $lensOption,
            lensCoating: $lensCoating,
            prescriptionProfile: $profile,
            prescription: $item['prescription'] ?? null,
        );
    }

    private function resolveBank(Request $request, ?PaymentMethod $paymentMethod): ?Bank
    {
        $bankId = $request->input('bank_id');

        if (!$paymentMethod?->requires_bank_selection) {
            return null;
        }

        if (!$bankId) {
            throw ValidationException::withMessages([
                'bank_id' => ['Rekening toko wajib dipilih untuk metode pembayaran ini.'],
            ]);
        }

        $bank = Bank::query()
            ->whereKey($bankId)
            ->where('is_active', true)
            ->first();

        if (!$bank) {
            throw ValidationException::withMessages([
                'bank_id' => ['Rekening toko tidak aktif atau tidak ditemukan.'],
            ]);
        }

        return $bank;
    }

    private function resolveShippingSelection(Request $request, bool $strict = false): array
    {
        $shippingCost = $request->input('shipping_cost');
        
        return [
            'shipping_rate_id' => null,
            'shipping_cost' => (float) ($shippingCost ?? 0),
            'courier' => $request->input('courier'),
            'courier_service' => $request->input('courier_service'),
        ];
    }
}
