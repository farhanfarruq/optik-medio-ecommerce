<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\WebhookEventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithPayment(User $user, string $orderStatus = 'unpaid', string $paymentStatus = 'pending', string $provider = 'xendit'): array
    {
        $category = Category::create([
            'name'      => 'Frame',
            'slug'      => 'frame-' . str()->random(6),
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id'              => $category->id,
            'name'                     => 'Frame Test',
            'slug'                     => 'frame-' . str()->random(6),
            'description'              => 'Test',
            'price'                    => 200000,
            'stock'                    => 5,
            'weight'                   => 300,
            'is_active'                => true,
            'is_prescription_required' => false,
        ]);

        $address = ShippingAddress::create([
            'user_id'        => $user->id,
            'recipient_name' => $user->name,
            'phone'          => '081234567890',
            'province'       => 'DKI Jakarta',
            'province_id'    => '31',
            'city'           => 'Jakarta Selatan',
            'city_id'        => '3174',
            'district'       => 'Kebayoran Baru',
            'district_id'    => '317401',
            'postal_code'    => '12110',
            'address'        => 'Jl. Test No. 1',
            'is_default'     => true,
        ]);

        $paymentMethod = PaymentMethod::create([
            'name'                    => 'Xendit',
            'code'                    => 'xendit-' . str()->random(6),
            'type'                    => 'gateway',
            'provider'                => $provider,
            'is_active'               => true,
            'requires_bank_selection' => false,
            'sort_order'              => 1,
        ]);

        $order = \App\Models\Order::create([
            'order_number'       => 'ORD-TEST-' . str()->random(6),
            'user_id'            => $user->id,
            'shipping_address_id'=> $address->id,
            'status'             => $orderStatus,
            'subtotal'           => 200000,
            'shipping_cost'      => 15000,
            'total_price'        => 215000,
            'courier'            => 'JNE',
            'courier_service'    => 'REG',
        ]);

        $payment = Payment::create([
            'order_id'          => $order->id,
            'payment_method_id' => $paymentMethod->id,
            'transaction_id'    => $order->order_number,
            'checkout_url'      => 'https://checkout.xendit.co/test',
            'provider'          => $provider,
            'gross_amount'      => 215000,
            'status'            => $paymentStatus,
        ]);

        return [$order, $payment];
    }

    public function test_user_can_get_payment_status_for_own_order(): void
    {
        $user = User::factory()->create();
        [$order] = $this->createOrderWithPayment($user);

        $this->actingAs($user)
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonStructure([
                'order_id',
                'order_number',
                'order_status',
                'is_payment_verified',
                'paid_at',
                'payment',
                'should_redirect',
                'is_expired',
            ])
            ->assertJsonPath('order_status', 'unpaid')
            ->assertJsonPath('should_redirect', false)
            ->assertJsonPath('is_expired', false);
    }

    public function test_payment_status_should_redirect_when_paid(): void
    {
        $user = User::factory()->create();
        [$order] = $this->createOrderWithPayment($user, 'paid', 'success');

        $this->actingAs($user)
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('should_redirect', true)
            ->assertJsonPath('order_status', 'paid');
    }

    public function test_payment_status_is_expired_when_cancelled(): void
    {
        $user = User::factory()->create();
        [$order] = $this->createOrderWithPayment($user, 'cancelled', 'expired');

        $this->actingAs($user)
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('is_expired', true);
    }

    public function test_user_cannot_get_payment_status_of_other_user_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        [$order] = $this->createOrderWithPayment($user1);

        $this->actingAs($user2)
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_get_payment_status(): void
    {
        $user = User::factory()->create();
        [$order] = $this->createOrderWithPayment($user);

        $this->getJson("/api/orders/{$order->id}/payment-status")
            ->assertStatus(401);
    }

    public function test_webhook_event_log_records_xendit_event(): void
    {
        $log = WebhookEventLog::record('xendit', 'ORD-TEST-001', 'PAID', [
            'external_id' => 'ORD-TEST-001',
            'status'      => 'PAID',
        ]);

        $this->assertDatabaseHas('webhook_event_logs', [
            'provider'          => 'xendit',
            'external_id'       => 'ORD-TEST-001',
            'processing_status' => 'received',
        ]);

        $log->markProcessed('Test processed');

        $this->assertDatabaseHas('webhook_event_logs', [
            'idempotency_key'   => 'xendit:ORD-TEST-001:paid',
            'processing_status' => 'processed',
        ]);
    }

    public function test_webhook_event_log_idempotency_key_is_unique(): void
    {
        WebhookEventLog::record('xendit', 'ORD-DUPE-001', 'PAID', ['status' => 'PAID']);
        WebhookEventLog::record('xendit', 'ORD-DUPE-001', 'PAID', ['status' => 'PAID']);

        $count = WebhookEventLog::where('external_id', 'ORD-DUPE-001')->count();
        $this->assertSame(1, $count);
    }

    public function test_webhook_event_log_already_processed_check(): void
    {
        $log = WebhookEventLog::record('xendit', 'ORD-PROC-001', 'PAID', []);
        $this->assertFalse(WebhookEventLog::alreadyProcessed('xendit:ORD-PROC-001:paid'));

        $log->markProcessed();
        $this->assertTrue(WebhookEventLog::alreadyProcessed('xendit:ORD-PROC-001:paid'));
    }
}
