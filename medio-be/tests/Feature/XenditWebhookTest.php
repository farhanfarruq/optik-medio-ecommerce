<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'cache.default' => 'array',
            'services.xendit.webhook_token' => 'test-webhook-token',
        ]);

        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shipping_addresses');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('province');
            $table->string('province_id');
            $table->string('city');
            $table->string('city_id');
            $table->string('district');
            $table->string('district_id')->nullable();
            $table->string('postal_code');
            $table->text('address');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shipping_address_id')->constrained();
            $table->string('status')->default('unpaid');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2);
            $table->timestamp('paid_at')->nullable();
            $table->boolean('is_payment_verified')->default(false);
            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id')->unique();
            $table->string('checkout_url')->nullable();
            $table->string('provider')->default('xendit');
            $table->string('payment_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->string('status')->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('product_price', 15, 2);
            $table->integer('quantity');
            $table->integer('weight')->default(0);
            $table->json('variant')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->json('prescription')->nullable();
            $table->unsignedBigInteger('parent_item_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shipping_addresses');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_xendit_webhook_rejects_invalid_token(): void
    {
        $this->postJson('/api/webhook/xendit', [
            'external_id' => 'ORD-1',
            'status' => 'PAID',
        ])->assertStatus(401);
    }

    public function test_xendit_webhook_is_idempotent_for_replayed_paid_payloads(): void
    {
        Mail::fake();

        $payment = $this->createPendingPayment();
        $payload = [
            'external_id' => $payment->transaction_id,
            'status' => 'PAID',
            'payment_channel' => 'VIRTUAL_ACCOUNT',
            'payment_method' => 'BCA',
        ];

        $this->withHeader('x-callback-token', 'test-webhook-token')
            ->postJson('/api/webhook/xendit', $payload)
            ->assertOk();

        $this->withHeader('x-callback-token', 'test-webhook-token')
            ->postJson('/api/webhook/xendit', $payload)
            ->assertOk();

        $payment->refresh();
        $order = $payment->order()->firstOrFail();

        $this->assertSame('success', $payment->status);
        $this->assertSame('paid', $order->status);
        $this->assertTrue((bool) $order->is_payment_verified);
        Mail::assertSentCount(1);
    }

    private function createPendingPayment(): Payment
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $addressId = \DB::table('shipping_addresses')->insertGetId([
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
            'address' => 'Jl. Contoh No. 1',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = \DB::table('orders')->insertGetId([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $user->id,
            'shipping_address_id' => $addressId,
            'status' => 'unpaid',
            'subtotal' => 150000,
            'shipping_cost' => 10000,
            'total_price' => 160000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = \DB::table('payments')->insertGetId([
            'order_id' => $orderId,
            'transaction_id' => 'ORD-TEST-001',
            'provider' => 'xendit',
            'gross_amount' => 160000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Payment::query()->findOrFail($paymentId);
    }
}
