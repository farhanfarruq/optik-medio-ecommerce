<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AffiliateController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BannerController;
use App\Http\Controllers\API\ComplainController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ShippingController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\DiscountController;
use App\Http\Controllers\API\AppSettingController;
use App\Http\Controllers\API\MasterDataController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\ReturnController;
use App\Http\Controllers\API\WishlistController;
use App\Http\Controllers\API\PromoController;
use Illuminate\Support\Facades\Route;

// ─── Public Endpoints ────────────────────────────────────────────────────────
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
    Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::middleware('throttle:3,10')->post('/resend-otp', [AuthController::class, 'resendOtp']);

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

// Products
Route::prefix('products')->group(function () {
    Route::get('/brands', [ProductController::class, 'brands']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{slug}', [ProductController::class, 'show']);
    Route::get('/{slug}/reviews', [ProductController::class, 'reviews']);
});

// Shipping cost (public)
Route::prefix('shipping')->group(function () {
    Route::get('/expeditions', [ShippingController::class, 'expeditions']);
    Route::get('/provinces', [ShippingController::class, 'provinces']);
    Route::get('/cities', [ShippingController::class, 'cities']);
    Route::get('/districts', [ShippingController::class, 'districts']);
    Route::post('/cost', [ShippingController::class, 'cost']);
});

// ─── Blog / Artikel (public) ─────────────────────────────────────────────────
Route::prefix('articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::get('/{slug}', [ArticleController::class, 'show']);
});

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
        Route::post('/toggle', [WishlistController::class, 'toggle']);
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::get('/check/{productId}', [WishlistController::class, 'check']);
    });
});
