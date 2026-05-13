<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_suggestions_only_return_buyable_active_products_and_categories(): void
    {
        $category = Category::create([
            'name' => 'Frame Titanium',
            'slug' => 'frame-titanium',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Frame Rahasia',
            'slug' => 'frame-rahasia',
            'is_active' => false,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Medio Titanium Flex',
            'slug' => 'medio-titanium-flex',
            'brand' => 'Medio',
            'price' => 700000,
            'stock' => 5,
            'weight' => 300,
            'tags' => ['titanium', 'flex'],
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Medio Titanium Nonaktif',
            'slug' => 'medio-titanium-nonaktif',
            'brand' => 'Medio',
            'price' => 700000,
            'stock' => 5,
            'weight' => 300,
            'is_active' => false,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Medio Titanium Habis',
            'slug' => 'medio-titanium-habis',
            'brand' => 'Medio',
            'price' => 700000,
            'stock' => 0,
            'weight' => 300,
            'is_active' => true,
        ]);

        $this->getJson('/api/products/search-suggestions?q=titanium')
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'medio-titanium-flex')
            ->assertJsonPath('categories.0.slug', 'frame-titanium')
            ->assertJsonMissing(['medio-titanium-nonaktif'])
            ->assertJsonMissing(['medio-titanium-habis'])
            ->assertJsonMissing(['frame-rahasia']);
    }

    public function test_product_compare_accepts_two_to_four_active_products(): void
    {
        $category = Category::create([
            'name' => 'Frame',
            'slug' => 'frame',
            'is_active' => true,
        ]);

        $first = Product::create([
            'category_id' => $category->id,
            'name' => 'Frame Round',
            'slug' => 'frame-round',
            'brand' => 'Medio',
            'frame_shape' => 'round',
            'price' => 500000,
            'stock' => 4,
            'weight' => 300,
            'is_active' => true,
        ]);

        $second = Product::create([
            'category_id' => $category->id,
            'name' => 'Frame Square',
            'slug' => 'frame-square',
            'brand' => 'Medio',
            'frame_shape' => 'square',
            'price' => 550000,
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

        $this->postJson('/api/products/compare', [
            'product_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'frame-round')
            ->assertJsonPath('products.1.slug', 'frame-square')
            ->assertJsonPath('attributes.0', 'brand');

        $this->postJson('/api/products/compare', [
            'product_ids' => [$first->id, $inactive->id],
        ])->assertStatus(422);
    }

    public function test_product_compare_rejects_more_than_four_products(): void
    {
        $this->postJson('/api/products/compare', [
            'product_ids' => [1, 2, 3, 4, 5],
        ])->assertStatus(422);
    }

    public function test_featured_products_can_be_prioritized_for_campaign_discovery(): void
    {
        $category = Category::create([
            'name' => 'Frame Campaign',
            'slug' => 'frame-campaign',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Regular Campaign Frame',
            'slug' => 'regular-campaign-frame',
            'brand' => 'Medio',
            'price' => 400000,
            'stock' => 5,
            'weight' => 300,
            'campaign_tags' => ['office'],
            'is_featured' => true,
            'recommendation_priority' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Priority Campaign Frame',
            'slug' => 'priority-campaign-frame',
            'brand' => 'Medio',
            'price' => 450000,
            'stock' => 5,
            'weight' => 300,
            'campaign_tags' => ['office'],
            'is_featured' => true,
            'recommendation_priority' => 900,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?featured=true&campaign_tag=office&sort=featured')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'priority-campaign-frame')
            ->assertJsonPath('data.1.slug', 'regular-campaign-frame');
    }
}
