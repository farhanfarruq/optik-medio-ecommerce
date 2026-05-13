<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_filters_endpoint_returns_active_filter_metadata(): void
    {
        $frames = Category::create([
            'name' => 'Frames',
            'slug' => 'frames',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'is_active' => false,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Frame A',
            'slug' => 'frame-a',
            'brand' => 'Medio',
            'gender' => 'unisex',
            'frame_shape' => 'round',
            'frame_material' => 'acetate',
            'frame_color' => 'black',
            'face_size_fit' => 'medium',
            'price' => 100000,
            'stock' => 5,
            'weight' => 500,
            'is_active' => true,
            'is_prescription_required' => true,
            'is_new' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Frame B',
            'slug' => 'frame-b',
            'brand' => 'Zeiss',
            'gender' => 'women',
            'frame_shape' => 'square',
            'frame_material' => 'titanium',
            'frame_color' => 'gold',
            'face_size_fit' => 'small',
            'price' => 250000,
            'stock' => 2,
            'weight' => 450,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Inactive Frame',
            'slug' => 'inactive-frame',
            'brand' => 'Hidden Brand',
            'gender' => 'men',
            'frame_shape' => 'aviator',
            'frame_material' => 'steel',
            'frame_color' => 'silver',
            'face_size_fit' => 'large',
            'price' => 500000,
            'stock' => 2,
            'weight' => 450,
            'is_active' => false,
        ]);

        $this->getJson('/api/products/filters')
            ->assertOk()
            ->assertJsonPath('categories.0.slug', 'frames')
            ->assertJsonPath('brands.0', 'Medio')
            ->assertJsonPath('brands.1', 'Zeiss')
            ->assertJsonPath('genders.0', 'unisex')
            ->assertJsonPath('genders.1', 'women')
            ->assertJsonPath('frame_shapes.0', 'round')
            ->assertJsonPath('frame_shapes.1', 'square')
            ->assertJsonPath('frame_materials.0', 'acetate')
            ->assertJsonPath('frame_materials.1', 'titanium')
            ->assertJsonPath('frame_colors.0', 'black')
            ->assertJsonPath('frame_colors.1', 'gold')
            ->assertJsonPath('face_size_fits.0', 'medium')
            ->assertJsonPath('face_size_fits.1', 'small')
            ->assertJsonPath('price_range.min', 100000)
            ->assertJsonPath('price_range.max', 250000)
            ->assertJsonPath('flags.prescription_supported', true)
            ->assertJsonPath('flags.new_arrivals', true)
            ->assertJsonMissingPath('categories.1')
            ->assertJsonMissing(['Hidden Brand'])
            ->assertJsonMissing(['aviator']);
    }

    public function test_product_index_can_filter_by_optical_attributes(): void
    {
        $frames = Category::create([
            'name' => 'Frames',
            'slug' => 'frames',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Round In Stock',
            'slug' => 'round-in-stock',
            'brand' => 'Medio',
            'gender' => 'unisex',
            'frame_shape' => 'round',
            'frame_material' => 'acetate',
            'frame_color' => 'black',
            'face_size_fit' => 'medium',
            'price' => 100000,
            'stock' => 5,
            'weight' => 500,
            'is_active' => true,
            'is_prescription_required' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Round Empty',
            'slug' => 'round-empty',
            'brand' => 'Medio',
            'gender' => 'unisex',
            'frame_shape' => 'round',
            'frame_material' => 'acetate',
            'frame_color' => 'black',
            'face_size_fit' => 'medium',
            'price' => 100000,
            'stock' => 0,
            'weight' => 500,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Square In Stock',
            'slug' => 'square-in-stock',
            'brand' => 'Medio',
            'gender' => 'women',
            'frame_shape' => 'square',
            'frame_material' => 'titanium',
            'frame_color' => 'gold',
            'face_size_fit' => 'small',
            'price' => 250000,
            'stock' => 5,
            'weight' => 450,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?gender=unisex&frame_shape=round&frame_material=acetate&frame_color=black&face_size_fit=medium&prescription_supported=true&in_stock_only=true')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'round-in-stock')
            ->assertJsonCount(1, 'data');
    }

    public function test_product_index_can_sort_by_price(): void
    {
        $frames = Category::create([
            'name' => 'Frames',
            'slug' => 'frames',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Expensive Frame',
            'slug' => 'expensive-frame',
            'price' => 500000,
            'stock' => 5,
            'weight' => 500,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Affordable Frame',
            'slug' => 'affordable-frame',
            'price' => 100000,
            'stock' => 5,
            'weight' => 500,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?sort=price_low')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'affordable-frame')
            ->assertJsonPath('data.1.slug', 'expensive-frame');
    }
}
