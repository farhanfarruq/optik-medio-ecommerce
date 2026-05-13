<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $category = Category::create([
            'name'      => 'Frame',
            'slug'      => 'frame-' . str()->random(6),
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'category_id'               => $category->id,
            'name'                      => 'Frame Test',
            'slug'                      => 'frame-test-' . str()->random(6),
            'description'               => 'Produk test',
            'price'                     => 150000,
            'stock'                     => 10,
            'weight'                    => 300,
            'is_active'                 => true,
            'is_prescription_required'  => false,
        ], $overrides));
    }

    public function test_authenticated_user_can_get_empty_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonStructure(['id', 'status', 'items', 'item_count']);
    }

    public function test_user_can_add_item_to_cart(): void
    {
        $user    = User::factory()->create();
        $product = $this->createProduct();

        $this->actingAs($user)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('item_count', 2);
    }

    public function test_add_item_rejects_quantity_exceeding_stock(): void
    {
        $user    = User::factory()->create();
        $product = $this->createProduct(['stock' => 3]);

        $this->actingAs($user)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok produk tidak mencukupi.');
    }

    public function test_user_can_update_item_quantity(): void
    {
        $user    = User::factory()->create();
        $product = $this->createProduct(['stock' => 10]);

        $addResponse = $this->actingAs($user)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 1,
            ])
            ->assertStatus(201);

        $itemId = $addResponse->json('items.0.id');

        $this->actingAs($user)
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('item_count', 3);
    }

    public function test_user_can_remove_item_from_cart(): void
    {
        $user    = User::factory()->create();
        $product = $this->createProduct();

        $addResponse = $this->actingAs($user)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 1,
            ])
            ->assertStatus(201);

        $itemId = $addResponse->json('items.0.id');

        $this->actingAs($user)
            ->deleteJson("/api/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('item_count', 0);
    }

    public function test_user_can_clear_cart(): void
    {
        $user    = User::factory()->create();
        $product = $this->createProduct();

        $this->actingAs($user)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 2,
            ]);

        $this->actingAs($user)
            ->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('message', 'Keranjang berhasil dikosongkan.');

        $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('item_count', 0);
    }

    public function test_sync_merges_local_cart_items_to_server(): void
    {
        $user     = User::factory()->create();
        $product1 = $this->createProduct(['stock' => 5]);
        $product2 = $this->createProduct(['stock' => 5]);

        $this->actingAs($user)
            ->postJson('/api/cart/sync', [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 2],
                    ['product_id' => $product2->id, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('item_count', 3);
    }

    public function test_sync_skips_inactive_products(): void
    {
        $user            = User::factory()->create();
        $activeProduct   = $this->createProduct(['stock' => 5]);
        $inactiveProduct = $this->createProduct(['is_active' => false]);

        $this->actingAs($user)
            ->postJson('/api/cart/sync', [
                'items' => [
                    ['product_id' => $activeProduct->id, 'quantity' => 1],
                    ['product_id' => $inactiveProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('item_count', 1);
    }

    public function test_cart_is_isolated_between_users(): void
    {
        $user1   = User::factory()->create();
        $user2   = User::factory()->create();
        $product = $this->createProduct();

        $this->actingAs($user1)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 3,
            ]);

        // User2 harus punya cart kosong
        $this->actingAs($user2)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('item_count', 0);
    }

    public function test_unauthenticated_user_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')->assertStatus(401);
        $this->postJson('/api/cart/items', [])->assertStatus(401);
        $this->deleteJson('/api/cart')->assertStatus(401);
    }
}
