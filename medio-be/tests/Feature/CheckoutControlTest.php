<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_rejects_shipping_address_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = $this->createProduct();
        $address = $this->createAddress($otherUser);

        $this->actingAs($user)
            ->postJson('/api/orders/calculate', [
                'shipping_address_id' => $address->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Alamat pengiriman tidak ditemukan atau bukan milik Anda.');
    }

    public function test_store_rejects_shipping_address_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = $this->createProduct();
        $address = $this->createAddress($otherUser);
        $paymentMethod = $this->createPaymentMethod();

        $this->actingAs($user)
            ->postJson('/api/orders', [
                'shipping_address_id' => $address->id,
                'shipping_cost' => 10000,
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Alamat pengiriman tidak ditemukan atau bukan milik Anda.');
    }

    public function test_calculate_caps_loyalty_points_to_available_balance_and_five_percent_subtotal(): void
    {
        $user = User::factory()->create(['loyalty_points' => 20]);
        $product = $this->createProduct(['price' => 100000, 'stock' => 5]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders/calculate', [
                'loyalty_points_used' => 999,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('loyalty_points_used', 5);
        $response->assertJsonPath('loyalty_discount_amount', 5000);
        $response->assertJsonPath('total_price', 95000);
    }

    public function test_second_checkout_is_rejected_when_stock_was_consumed_by_first_checkout(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $product = $this->createProduct(['stock' => 1]);
        $paymentMethod = $this->createPaymentMethod();
        $firstAddress = $this->createAddress($firstUser);
        $secondAddress = $this->createAddress($secondUser);

        $this->actingAs($firstUser)
            ->postJson('/api/orders', [
                'shipping_address_id' => $firstAddress->id,
                'shipping_cost' => 10000,
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $this->assertSame(0, $product->fresh()->stock);

        $this->actingAs($secondUser)
            ->postJson('/api/orders', [
                'shipping_address_id' => $secondAddress->id,
                'shipping_cost' => 10000,
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    private function createProduct(array $overrides = []): Product
    {
        $category = Category::create([
            'name' => 'Frame',
            'slug' => 'frame-' . str()->random(8),
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Frame Test',
            'slug' => 'frame-test-' . str()->random(8),
            'description' => 'Produk test',
            'price' => 100000,
            'stock' => 10,
            'weight' => 500,
            'is_active' => true,
            'is_prescription_required' => false,
        ], $overrides));
    }

    private function createAddress(User $user): ShippingAddress
    {
        return ShippingAddress::create([
            'user_id' => $user->id,
            'recipient_name' => $user->name,
            'phone' => '081234567890',
            'province' => 'DKI Jakarta',
            'province_id' => '31',
            'city' => 'Jakarta Selatan',
            'city_id' => '3174',
            'district' => 'Kebayoran Baru',
            'district_id' => '317401',
            'postal_code' => '12110',
            'address' => 'Jl. Test No. 1',
            'is_default' => true,
        ]);
    }

    private function createPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'Transfer Bank',
            'code' => 'manual-transfer-' . str()->random(8),
            'type' => 'manual_transfer',
            'provider' => 'manual',
            'is_active' => true,
            'requires_bank_selection' => false,
            'sort_order' => 1,
        ]);
    }
}
