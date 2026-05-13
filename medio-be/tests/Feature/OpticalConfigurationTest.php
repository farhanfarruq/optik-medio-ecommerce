<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PrescriptionProfile;
use App\Models\Product;
use App\Models\ProductCompatibility;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_optical_configure_returns_price_breakdown_and_snapshot(): void
    {
        $user = User::factory()->create();
        $frame = $this->createProduct(['price' => 500000]);
        $lensOption = LensOption::create([
            'name' => 'Single Vision',
            'type' => 'single_vision',
            'base_price' => 250000,
            'is_active' => true,
        ]);
        $coating = LensCoating::create([
            'name' => 'Anti Scratch',
            'price' => 75000,
            'is_active' => true,
        ]);
        ProductCompatibility::create([
            'frame_product_id' => $frame->id,
            'lens_option_id' => $lensOption->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/optical/configure', [
                'frame_product_id' => $frame->id,
                'lens_option_id' => $lensOption->id,
                'lens_coating_id' => $coating->id,
            ])
            ->assertOk()
            ->assertJsonPath('compatible', true)
            ->assertJsonPath('price_breakdown.frame_price', 500000)
            ->assertJsonPath('price_breakdown.lens_price', 250000)
            ->assertJsonPath('price_breakdown.coating_price', 75000)
            ->assertJsonPath('price_breakdown.total', 825000)
            ->assertJsonPath('configuration_snapshot.lens_option.id', $lensOption->id);
    }

    public function test_order_persists_optical_configuration_snapshot(): void
    {
        $user = User::factory()->create();
        $frame = $this->createProduct([
            'price' => 500000,
            'is_prescription_required' => true,
        ]);
        $address = $this->createAddress($user);
        $paymentMethod = $this->createPaymentMethod();
        $lensOption = LensOption::create([
            'name' => 'Single Vision',
            'type' => 'single_vision',
            'base_price' => 250000,
            'is_active' => true,
        ]);
        $coating = LensCoating::create([
            'name' => 'Anti Scratch',
            'price' => 75000,
            'is_active' => true,
        ]);
        $profile = PrescriptionProfile::create([
            'user_id' => $user->id,
            'label' => 'Main',
            'lens_type' => 'single_vision',
            'right_sphere' => -1,
            'left_sphere' => -1,
            'pd_single' => 63,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'shipping_address_id' => $address->id,
                'shipping_cost' => 10000,
                'payment_method_id' => $paymentMethod->id,
                'items' => [[
                    'product_id' => $frame->id,
                    'quantity' => 1,
                    'lens_option_id' => $lensOption->id,
                    'lens_coating_id' => $coating->id,
                    'prescription_profile_id' => $profile->id,
                ]],
            ])
            ->assertCreated();

        $orderId = $response->json('order.id') ?? Order::query()->firstOrFail()->id;

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $frame->id,
            'lens_option_id' => $lensOption->id,
            'lens_coating_id' => $coating->id,
            'prescription_profile_id' => $profile->id,
            'product_price' => 825000,
            'lens_price' => 250000,
            'coating_price' => 75000,
        ]);
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
