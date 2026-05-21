<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingProtectionComplaintTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $user, bool $withProtection): Order
    {
        $address = ShippingAddress::create([
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

        return Order::create([
            'order_number' => 'ORD-SP-' . str()->upper(str()->random(8)),
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'status' => 'delivered',
            'subtotal' => 500000,
            'shipping_cost' => 20000,
            'shipping_protection_opted' => $withProtection,
            'shipping_protection_fee' => $withProtection ? 3000 : 0,
            'total_price' => $withProtection ? 523000 : 520000,
            'courier' => 'JNE',
            'courier_service' => 'REG',
        ]);
    }

    public function test_user_can_create_shipping_protection_claim_for_protected_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, true);

        $this->actingAs($user)
            ->postJson('/api/complaints', [
                'order_id' => $order->id,
                'complaint_type' => 'shipping_protection',
                'subject' => 'Klaim Proteksi Pengiriman',
                'message' => 'Paket datang rusak di bagian frame.',
                'contact_phone' => '081234567890',
            ])
            ->assertCreated()
            ->assertJsonPath('data.complaint_type', 'shipping_protection');

        $this->assertDatabaseHas('complains', [
            'order_id' => $order->id,
            'complaint_type' => 'shipping_protection',
            'status' => 'open',
        ]);
    }

    public function test_shipping_protection_claim_is_rejected_for_order_without_protection(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, false);

        $this->actingAs($user)
            ->postJson('/api/complaints', [
                'order_id' => $order->id,
                'complaint_type' => 'shipping_protection',
                'subject' => 'Klaim Proteksi Pengiriman',
                'message' => 'Paket datang rusak di bagian frame.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini tidak menggunakan proteksi pengiriman.');
    }
}
