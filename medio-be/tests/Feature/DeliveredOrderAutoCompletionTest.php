<?php

namespace Tests\Feature;

use App\Jobs\SendReviewRequest;
use App\Mail\ReviewRequestMail;
use App\Models\Category;
use App\Models\Complain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveredOrderAutoCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function createDeliveredOrder(array $attributes = []): Order
    {
        $user = User::factory()->create();

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

        $deliveredAt = now()->subDays(4);

        $order = Order::create(array_merge([
            'order_number'        => 'ORD-TEST-' . str()->random(6),
            'user_id'             => $user->id,
            'shipping_address_id' => $address->id,
            'status'              => 'delivered',
            'subtotal'            => 200000,
            'shipping_cost'       => 15000,
            'total_price'         => 215000,
            'courier'             => 'JNE',
            'courier_service'     => 'REG',
            'delivered_at'        => $deliveredAt,
        ], $attributes));

        $order->forceFill([
            'delivered_at' => $attributes['delivered_at'] ?? $deliveredAt,
            'updated_at'   => $attributes['updated_at'] ?? $deliveredAt,
        ])->saveQuietly();

        OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'product_price' => $product->price,
            'quantity'      => 1,
            'weight'        => $product->weight,
            'subtotal'      => $product->price,
        ]);

        return $order;
    }

    public function test_delivered_order_after_three_days_is_completed_and_review_email_is_sent(): void
    {
        Mail::fake();

        $order = $this->createDeliveredOrder();

        (new SendReviewRequest())->handle();

        $order->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->review_requested_at);

        Mail::assertSent(ReviewRequestMail::class, fn (ReviewRequestMail $mail) => $mail->order->is($order));
    }

    public function test_delivered_order_with_active_return_or_complain_is_not_completed(): void
    {
        Mail::fake();

        $returnOrder = $this->createDeliveredOrder();
        ReturnRequest::create([
            'user_id'     => $returnOrder->user_id,
            'order_id'    => $returnOrder->id,
            'reason'      => 'Ukuran tidak cocok',
            'description' => 'Ingin retur',
            'status'      => 'pending',
        ]);

        $complainOrder = $this->createDeliveredOrder();
        Complain::create([
            'user_id'       => $complainOrder->user_id,
            'order_id'      => $complainOrder->id,
            'subject'       => 'Produk bermasalah',
            'message'       => 'Mohon dicek',
            'contact_phone' => '081234567890',
            'status'        => 'open',
        ]);

        (new SendReviewRequest())->handle();

        $this->assertSame('delivered', $returnOrder->refresh()->status);
        $this->assertSame('delivered', $complainOrder->refresh()->status);

        Mail::assertNothingSent();
    }
}
