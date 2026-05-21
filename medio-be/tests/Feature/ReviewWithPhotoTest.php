<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewWithPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function createDeliveredOrderItem(User $user): OrderItem
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

        $order = Order::create([
            'order_number'        => 'ORD-TEST-' . str()->random(6),
            'user_id'             => $user->id,
            'shipping_address_id' => $address->id,
            'status'              => 'delivered',
            'subtotal'            => 200000,
            'shipping_cost'       => 15000,
            'total_price'         => 215000,
            'courier'             => 'JNE',
            'courier_service'     => 'REG',
        ]);

        return OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'product_price' => $product->price,
            'quantity'      => 1,
            'weight'        => $product->weight,
            'subtotal'      => $product->price,
        ]);
    }

    public function test_user_can_submit_review_without_photo(): void
    {
        $user     = User::factory()->create();
        $item     = $this->createDeliveredOrderItem($user);

        $this->actingAs($user)
            ->postJson('/api/reviews', [
                'order_item_id' => $item->id,
                'rating'        => 5,
                'comment'       => 'Produk bagus!',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Ulasan berhasil dikirim. Terima kasih!');
    }

    public function test_user_can_submit_review_with_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $item = $this->createDeliveredOrderItem($user);

        $this->actingAs($user)
            ->post('/api/reviews', [
                'order_item_id' => $item->id,
                'rating'        => 4,
                'comment'       => 'Bagus dengan foto!',
                'images'        => [
                    UploadedFile::fake()->image('review1.jpg', 800, 600),
                    UploadedFile::fake()->image('review2.jpg', 800, 600),
                ],
            ])
            ->assertStatus(201);

        $review = ProductReview::where('order_item_id', $item->id)->first();
        $this->assertNotNull($review);
        $this->assertIsArray($review->images);
        $this->assertCount(2, $review->images);
    }

    public function test_review_rejects_more_than_3_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $item = $this->createDeliveredOrderItem($user);

        $this->actingAs($user)
            ->post('/api/reviews', [
                'order_item_id' => $item->id,
                'rating'        => 3,
                'images'        => [
                    UploadedFile::fake()->image('r1.jpg'),
                    UploadedFile::fake()->image('r2.jpg'),
                    UploadedFile::fake()->image('r3.jpg'),
                    UploadedFile::fake()->image('r4.jpg'), // lebih dari 3
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_review_only_allowed_for_delivered_orders(): void
    {
        $user     = User::factory()->create();
        $item     = $this->createDeliveredOrderItem($user);

        // Ubah status order ke processing
        $item->order->update(['status' => 'processing']);

        $this->actingAs($user)
            ->postJson('/api/reviews', [
                'order_item_id' => $item->id,
                'rating'        => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda hanya bisa memberikan ulasan setelah pesanan diterima.');
    }

    public function test_user_cannot_review_same_item_twice(): void
    {
        $user = User::factory()->create();
        $item = $this->createDeliveredOrderItem($user);

        $this->actingAs($user)
            ->postJson('/api/reviews', ['order_item_id' => $item->id, 'rating' => 5])
            ->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/reviews', ['order_item_id' => $item->id, 'rating' => 4])
            ->assertStatus(422);
    }

    public function test_public_can_get_approved_reviews(): void
    {
        $user    = User::factory()->create();
        $item    = $this->createDeliveredOrderItem($user);
        $product = $item->product;

        ProductReview::create([
            'user_id'       => $user->id,
            'product_id'    => $product->id,
            'order_item_id' => $item->id,
            'rating'        => 5,
            'comment'       => 'Bagus!',
            'is_approved'   => true,
        ]);

        $this->getJson("/api/products/{$product->slug}/reviews")
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }
}
