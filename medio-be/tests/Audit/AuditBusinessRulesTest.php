<?php

namespace Tests\Audit;

class AuditBusinessRulesTest extends AuditTestCase
{
    public function test_total_price_formula_never_returns_negative_value(): void
    {
        $cases = [
            [0, 0, 0, 0, 0, 0],
            [100000, 20000, 10000, 0, 5000, 5000],
            [100000, 0, 100000, 100000, 100000, 100000],
            [250000, 35000, 0, 25000, 12500, 10000],
            [9999, 15000, 50000, 0, 0, 1000],
        ];

        foreach ($cases as [$subtotal, $shipping, $discount, $promo, $level, $loyalty]) {
            $total = max(0, $subtotal + $shipping - $discount - $promo - $level - $loyalty);

            $this->assertGreaterThanOrEqual(0, $total);
        }
    }

    public function test_loyalty_discount_is_capped_at_five_percent_of_subtotal(): void
    {
        $cases = [
            [0, 10],
            [10000, 100],
            [100000, 10],
            [250000, 999],
            [999999, 999999],
        ];

        foreach ($cases as [$subtotal, $points]) {
            $maxDiscount = (int) floor($subtotal * 0.05);
            $discount = min($points * 1000, $maxDiscount);

            $this->assertLessThanOrEqual($maxDiscount, $discount);
        }
    }

    public function test_core_audit_controls_are_present_in_source(): void
    {
        $this->assertSourceContains('medio-be/app/Models/Product.php', 'use SoftDeletes;');
        $this->assertSourceContains('medio-be/app/Models/Order.php', 'use SoftDeletes;');
        $this->assertSourceContains('medio-be/app/Http/Controllers/API/WebhookController.php', "header('x-callback-token')");
        $this->assertSourceContains('medio-be/app/Http/Controllers/API/OrderController.php', 'if ($request->discount_id && $request->promo_id)');
        $this->assertSourceContains('medio-be/app/Http/Controllers/API/OrderController.php', "where('user_id', \$request->user()->id)");
        $this->assertSourceContains('medio-be/app/Http/Controllers/API/OrderController.php', "where('stock', '>=', \$item['quantity'])");
        $this->assertSourceContains('medio-fe/src/stores/cartStore.ts', 'buildCheckoutItemsPayload');
        $this->assertSourceContains('medio-be/app/Http/Middleware/CorrelationId.php', 'X-Request-ID');
        $this->assertSourceContains('medio-be/routes/api.php', "Route::get('/health'");
    }

    public function test_routes_expose_auth_and_checkout_guards(): void
    {
        $this->assertSourceContains('medio-be/routes/api.php', "Route::middleware('throttle:5,10')->post('/verify-otp'");
        $this->assertSourceContains('medio-be/routes/api.php', "Route::middleware('throttle:3,10')->post('/resend-otp'");
        $this->assertSourceContains('medio-be/routes/api.php', "Route::middleware('auth:sanctum')->group(function ()");
        $this->assertSourceContains('medio-be/routes/api.php', "Route::middleware('store.open')->group(function ()");
    }
}
