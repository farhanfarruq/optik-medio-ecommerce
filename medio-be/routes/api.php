<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AffiliateController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BannerController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\ComplainController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\BusinessEventController;
use App\Http\Controllers\API\MerchantFeedController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ReferralController;
use App\Http\Controllers\API\ShippingController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\DiscountController;
use App\Http\Controllers\API\AppSettingController;
use App\Http\Controllers\API\MasterDataController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\ReturnController;
use App\Http\Controllers\API\WarrantyController;
use App\Http\Controllers\API\WishlistController;
use App\Http\Controllers\API\PromoController;
use App\Http\Controllers\API\PrescriptionController;
use App\Http\Controllers\API\OpticalController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ─── Public Endpoints ────────────────────────────────────────────────────────
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'time' => now()->toISOString(),
            'app_env' => app()->environment(),
            'database' => 'ok',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'time' => now()->toISOString(),
            'app_env' => app()->environment(),
            'database' => 'error',
        ], 503);
    }
});

Route::get('/settings', [AppSettingController::class, 'index']);
Route::get('/promos', [PromoController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/faqs', [FaqController::class, 'index']);
Route::post('/webhook/xendit', [WebhookController::class, 'xendit']);
Route::get('/banks', [MasterDataController::class, 'banks']);
Route::get('/payment-methods', [MasterDataController::class, 'paymentMethods']);
Route::get('/store-status', [MasterDataController::class, 'storeStatus']);

// Auth
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:3,1')->post('/register', [AuthController::class, 'register']);
    Route::middleware('throttle:20,1')->post('/login', [AuthController::class, 'login']);
    Route::middleware('throttle:10,10')->post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::middleware('throttle:5,10')->post('/resend-otp', [AuthController::class, 'resendOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
    });
});

// Discount validation
Route::middleware('throttle:10,1')->post('/discounts/validate', [DiscountController::class, 'validateCode']);

// Categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/level-members', [\App\Http\Controllers\API\LevelMemberController::class, 'index']);

// Products
Route::prefix('products')->group(function () {
    Route::get('/brands', [ProductController::class, 'brands']);
    Route::get('/filters', [ProductController::class, 'filters']);
    Route::get('/search-suggestions', [ProductController::class, 'searchSuggestions']);
    Route::post('/compare', [ProductController::class, 'compare']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{slug}/recommendations', [ProductController::class, 'recommendations']);
    Route::get('/{slug}', [ProductController::class, 'show']);
    Route::get('/{slug}/reviews', [ProductController::class, 'reviews']);
});

Route::get('/wishlist/shared/{token}', [WishlistController::class, 'shared']);

// Shipping cost (public)
Route::prefix('shipping')->group(function () {
    Route::get('/expeditions', [ShippingController::class, 'expeditions']);
    Route::get('/provinces', [ShippingController::class, 'provinces']);
    Route::get('/cities', [ShippingController::class, 'cities']);
    Route::get('/districts', [ShippingController::class, 'districts']);
    Route::middleware('throttle:15,1')->post('/cost', [ShippingController::class, 'cost']);
});

// ─── Blog / Artikel (public) ─────────────────────────────────────────────────
Route::prefix('articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::get('/{slug}', [ArticleController::class, 'show'])->middleware('throttle:30,1');
});

// ─── Product Reviews (public read) ───────────────────────────────────────────
Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);

// ─── Merchant Feed (public, rate-limited) ────────────────────────────────────
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/merchant-feed', [MerchantFeedController::class, 'index']);
    Route::get('/merchant-feed/diagnostics', [MerchantFeedController::class, 'diagnostics']);
});

// ─── Referral (public validate) ──────────────────────────────────────────────
Route::get('/referral/validate/{code}', [ReferralController::class, 'validate']);

// ─── Business Events (public, rate-limited) ───────────────────────────────────
Route::middleware('throttle:120,1')->post('/events', [BusinessEventController::class, 'store']);

// ─── Store Branches (public) ──────────────────────────────────────────────────
Route::get('/branches', [AppointmentController::class, 'branches']);
Route::get('/branches/{id}/availability', [AppointmentController::class, 'availability']);

// ─── Prescription Validation (public) ────────────────────────────────────────
Route::post('/prescriptions/validate', [AppointmentController::class, 'validatePrescription']);

// ─── Protected Endpoints (requires auth) ─────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::middleware('store.open')->group(function () {
            Route::post('/calculate', [OrderController::class, 'calculate']);
            Route::post('/', [OrderController::class, 'store']);
        });
        Route::get('/loyalty-history', [OrderController::class, 'loyaltyHistory']);
        Route::post('/{id}/payment-proof', [OrderController::class, 'uploadPaymentProof']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::get('/{id}/payment-status', [OrderController::class, 'paymentStatus']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/{id}/sync-payment', [OrderController::class, 'syncPayment']);
        Route::post('/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Addresses
    Route::prefix('addresses')->group(function () {
        Route::get('/', [ShippingController::class, 'getAddresses']);
        Route::post('/', [ShippingController::class, 'storeAddress']);
        Route::put('/{id}', [ShippingController::class, 'updateAddress']);
        Route::delete('/{id}', [ShippingController::class, 'destroyAddress']);
    });

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Optical configuration
    Route::get('/optical/lens-coatings', [OpticalController::class, 'coatings']);
    Route::post('/optical/configure', [OpticalController::class, 'configure']);

    // Prescription Profiles
    Route::prefix('prescriptions')->group(function () {
        Route::get('/', [PrescriptionController::class, 'index']);
        Route::post('/', [PrescriptionController::class, 'store']);
        Route::get('/{id}', [PrescriptionController::class, 'show']);
        Route::put('/{id}', [PrescriptionController::class, 'update']);
        Route::delete('/{id}', [PrescriptionController::class, 'destroy']);
        Route::post('/{id}/set-default', [PrescriptionController::class, 'setDefault']);
    });

    // Return & Refund
    Route::post('/returns', [ReturnController::class, 'store']);

    // Affiliate
    Route::prefix('affiliate')->group(function () {
        Route::get('/dashboard', [AffiliateController::class, 'dashboard']);
        Route::post('/apply', [AffiliateController::class, 'apply']);
        Route::get('/commissions', [AffiliateController::class, 'commissions']);
        Route::post('/commissions/request', [AffiliateController::class, 'requestPayout']);
    });

    // Complaints
    Route::prefix('complaints')->group(function () {
        Route::get('/', [ComplainController::class, 'index']);
        Route::post('/', [ComplainController::class, 'store']);
        Route::get('/{id}', [ComplainController::class, 'show']);
    });

    // Wishlist
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::post('/share', [WishlistController::class, 'createShareLink']);
        Route::post('/toggle', [WishlistController::class, 'toggle']);
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::get('/check/{productId}', [WishlistController::class, 'check']);
    });

    // Server-side Cart (persistence & sync)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/sync', [CartController::class, 'sync']);
    });

    // Referral (protected)
    Route::prefix('referral')->group(function () {
        Route::get('/my-code', [ReferralController::class, 'myCode']);
        Route::post('/use', [ReferralController::class, 'use']);
    });

    // Appointments (protected)
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{id}', [AppointmentController::class, 'show']);
        Route::delete('/{id}', [AppointmentController::class, 'cancel']);
    });

    // Warranties & Service Claims (protected)
    Route::get('/warranties', [WarrantyController::class, 'index']);
    Route::get('/warranties/{id}', [WarrantyController::class, 'show']);
    Route::get('/service-claims', [WarrantyController::class, 'claims']);
    Route::post('/service-claims', [WarrantyController::class, 'storeClaim']);
});
