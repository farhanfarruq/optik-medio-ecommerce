<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_share_only_active_buyable_wishlist_products_without_user_data(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Frame',
            'slug' => 'frame',
            'is_active' => true,
        ]);

        $active = Product::create([
            'category_id' => $category->id,
            'name' => 'Frame Public',
            'slug' => 'frame-public',
            'brand' => 'Medio',
            'price' => 500000,
            'stock' => 4,
            'weight' => 300,
            'is_active' => true,
        ]);

        $inactive = Product::create([
            'category_id' => $category->id,
            'name' => 'Frame Hidden',
            'slug' => 'frame-hidden',
            'brand' => 'Medio',
            'price' => 500000,
            'stock' => 4,
            'weight' => 300,
            'is_active' => false,
        ]);

        Wishlist::create(['user_id' => $user->id, 'product_id' => $active->id]);
        Wishlist::create(['user_id' => $user->id, 'product_id' => $inactive->id]);

        $token = $this->actingAs($user)
            ->postJson('/api/wishlist/share')
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->json('token');

        $this->getJson('/api/wishlist/shared/' . urlencode($token))
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'frame-public')
            ->assertJsonMissing(['frame-hidden'])
            ->assertJsonMissing(['user_id'])
            ->assertJsonMissing(['email']);
    }

    public function test_invalid_shared_wishlist_token_returns_not_found(): void
    {
        $this->getJson('/api/wishlist/shared/not-a-valid-token')
            ->assertNotFound();
    }
}
