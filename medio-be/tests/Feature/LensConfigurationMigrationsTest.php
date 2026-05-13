<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\Product;
use App\Models\ProductCompatibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LensConfigurationMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lens_configuration_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('lens_options', [
            'id',
            'name',
            'type',
            'base_price',
            'prescription_rules',
            'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('lens_coatings', [
            'id',
            'name',
            'price',
            'description',
            'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('product_compatibilities', [
            'id',
            'frame_product_id',
            'lens_option_id',
            'compatibility_rule',
        ]));

        $this->assertTrue(Schema::hasColumn('products', 'prescription_rules'));
    }

    public function test_lens_configuration_models_cast_and_relate_data(): void
    {
        $category = Category::create([
            'name' => 'Frame',
            'slug' => 'frame',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Frame Test',
            'slug' => 'frame-test',
            'price' => 100000,
            'stock' => 5,
            'weight' => 500,
            'is_active' => true,
            'is_prescription_required' => true,
            'prescription_rules' => ['max_sphere' => '4.00'],
        ]);

        $lensOption = LensOption::create([
            'name' => 'Progressive Premium',
            'type' => 'progressive',
            'base_price' => 450000,
            'prescription_rules' => ['min_add' => '1.00'],
            'is_active' => true,
        ]);

        LensCoating::create([
            'name' => 'Anti Scratch',
            'price' => 75000,
            'is_active' => true,
        ]);

        ProductCompatibility::create([
            'frame_product_id' => $product->id,
            'lens_option_id' => $lensOption->id,
            'compatibility_rule' => ['requires_pd' => 'true'],
        ]);

        $this->assertSame(['max_sphere' => '4.00'], $product->fresh()->prescription_rules);
        $this->assertSame(['min_add' => '1.00'], $lensOption->fresh()->prescription_rules);
        $this->assertSame('Progressive Premium', $product->fresh()->compatibleLensOptions()->first()->name);
        $this->assertSame('Frame Test', $lensOption->fresh()->compatibleFrames()->first()->name);
    }
}
