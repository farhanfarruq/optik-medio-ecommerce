<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
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
            'slug'                     => 'frame-' . str()->random(6),
            'description'              => 'Test',
            'price'                    => 200000,
            'stock'                    => 10,
            'low_stock_threshold'      => 5,
            'weight'                   => 300,
            'is_active'                => true,
            'is_prescription_required' => false,
        ], $overrides));
    }

    public function test_product_has_low_stock_threshold_field(): void
    {
        $product = $this->createProduct(['stock' => 3, 'low_stock_threshold' => 5]);
        $this->assertSame(5, $product->low_stock_threshold);
    }

    public function test_is_low_stock_returns_true_when_stock_at_or_below_threshold(): void
    {
        $product = $this->createProduct(['stock' => 5, 'low_stock_threshold' => 5]);
        $this->assertTrue($product->isLowStock());

        $product->update(['stock' => 3]);
        $this->assertTrue($product->fresh()->isLowStock());
    }

    public function test_is_low_stock_returns_false_when_stock_above_threshold(): void
    {
        $product = $this->createProduct(['stock' => 10, 'low_stock_threshold' => 5]);
        $this->assertFalse($product->isLowStock());
    }

    public function test_stock_adjustment_increases_stock(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(['stock' => 5]);

        $adj = StockAdjustment::adjust(
            product:        $product,
            quantityChange: 10,
            reason:         'import',
            notes:          'Stok baru masuk',
            adjustedBy:     $admin->id,
        );

        $this->assertSame(5, $adj->quantity_before);
        $this->assertSame(10, $adj->quantity_change);
        $this->assertSame(15, $adj->quantity_after);
        $this->assertSame(15, $product->fresh()->stock);
    }

    public function test_stock_adjustment_decreases_stock(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(['stock' => 10]);

        $adj = StockAdjustment::adjust(
            product:        $product,
            quantityChange: -3,
            reason:         'correction',
            adjustedBy:     $admin->id,
        );

        $this->assertSame(10, $adj->quantity_before);
        $this->assertSame(-3, $adj->quantity_change);
        $this->assertSame(7, $adj->quantity_after);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_stock_adjustment_cannot_go_below_zero(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(['stock' => 2]);

        $adj = StockAdjustment::adjust(
            product:        $product,
            quantityChange: -10, // lebih dari stok
            reason:         'correction',
            adjustedBy:     $admin->id,
        );

        // Stok tidak boleh negatif
        $this->assertSame(0, $adj->quantity_after);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_stock_adjustment_creates_log_record(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(['stock' => 5]);

        StockAdjustment::adjust(
            product:        $product,
            quantityChange: 5,
            reason:         'import',
            adjustedBy:     $admin->id,
        );

        $this->assertDatabaseHas('stock_adjustments', [
            'product_id'      => $product->id,
            'adjusted_by'     => $admin->id,
            'quantity_before' => 5,
            'quantity_change' => 5,
            'quantity_after'  => 10,
            'reason'          => 'import',
        ]);
    }

    public function test_product_has_stock_adjustments_relation(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(['stock' => 5]);

        StockAdjustment::adjust($product, 3, 'import', null, $admin->id);
        StockAdjustment::adjust($product->fresh(), -1, 'correction', null, $admin->id);

        $this->assertCount(2, $product->fresh()->stockAdjustments);
    }

    public function test_reason_label_attribute_returns_human_readable_text(): void
    {
        $adj = new StockAdjustment(['reason' => 'manual_adjustment']);
        $this->assertSame('Penyesuaian Manual', $adj->reason_label);

        $adj->reason = 'import';
        $this->assertSame('Import Stok', $adj->reason_label);

        $adj->reason = 'order_returned';
        $this->assertSame('Retur Pesanan', $adj->reason_label);
    }
}
