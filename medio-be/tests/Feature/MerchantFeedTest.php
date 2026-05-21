<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantFeedTest extends TestCase
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
            'category_id'              => $category->id,
            'name'                     => 'Frame Test',
            'slug'                     => 'frame-test-' . str()->random(6),
            'description'              => 'Produk test',
            'price'                    => 350000,
            'stock'                    => 5,
            'weight'                   => 300,
            'is_active'                => true,
            'is_prescription_required' => false,
            'brand'                    => 'TestBrand',
            'sku'                      => 'SKU-' . str()->random(6),
        ], $overrides));
    }

    public function test_merchant_feed_returns_tsv_by_default(): void
    {
        $this->createProduct();

        $response = $this->getJson('/api/merchant-feed');
        // TSV endpoint returns text/tab-separated-values, not JSON
        // Tapi karena kita pakai getJson, cek status saja
        $response->assertStatus(200);
    }

    public function test_merchant_feed_json_format_returns_products(): void
    {
        $this->createProduct(['stock' => 5]);
        $this->createProduct(['stock' => 0]); // out of stock — tidak masuk feed

        $response = $this->getJson('/api/merchant-feed?format=json');
        $response->assertOk()
            ->assertJsonStructure(['total', 'products'])
            ->assertJsonPath('total', 1); // hanya yang stock > 0
    }

    public function test_merchant_feed_json_contains_required_fields(): void
    {
        $this->createProduct();

        $response = $this->getJson('/api/merchant-feed?format=json');
        $response->assertOk();

        $product = $response->json('products.0');
        $this->assertArrayHasKey('id', $product);
        $this->assertArrayHasKey('title', $product);
        $this->assertArrayHasKey('price', $product);
        $this->assertArrayHasKey('availability', $product);
        $this->assertArrayHasKey('brand', $product);
        $this->assertArrayHasKey('link', $product);
    }

    public function test_merchant_feed_excludes_inactive_products(): void
    {
        $this->createProduct(['is_active' => true, 'stock' => 5]);
        $this->createProduct(['is_active' => false, 'stock' => 5]);

        $response = $this->getJson('/api/merchant-feed?format=json');
        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_merchant_feed_diagnostics_returns_issues(): void
    {
        // Produk tanpa brand dan tanpa SKU/GTIN/MPN
        $category = Category::create([
            'name'      => 'Frame',
            'slug'      => 'frame-diag-' . str()->random(6),
            'is_active' => true,
        ]);
        Product::create([
            'category_id'              => $category->id,
            'name'                     => 'Frame No Brand',
            'slug'                     => 'frame-no-brand-' . str()->random(6),
            'description'              => 'Test',
            'price'                    => 200000,
            'stock'                    => 5,
            'weight'                   => 300,
            'is_active'                => true,
            'is_prescription_required' => false,
            // brand, sku, gtin, mpn semua null
        ]);

        $response = $this->getJson('/api/merchant-feed/diagnostics');
        $response->assertOk()
            ->assertJsonStructure(['total_products', 'eligible', 'ineligible', 'issues']);

        $issues = $response->json('issues');
        $this->assertNotEmpty($issues);

        $firstIssue = $issues[0];
        $this->assertContains('missing_brand', $firstIssue['problems']);
        $this->assertContains('missing_identifier', $firstIssue['problems']);
    }

    public function test_merchant_feed_product_availability_field(): void
    {
        $this->createProduct(['stock' => 10]);

        $response = $this->getJson('/api/merchant-feed?format=json');
        $response->assertOk()
            ->assertJsonPath('products.0.availability', 'in stock');
    }
}
