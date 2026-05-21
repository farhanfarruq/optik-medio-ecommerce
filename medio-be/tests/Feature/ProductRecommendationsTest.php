<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LensOption;
use App\Models\Product;
use App\Models\ProductCompatibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_recommendations_return_similar_frames_and_compatible_lenses(): void
    {
        $frames = Category::create([
            'name' => 'Frame Kacamata',
            'slug' => 'frame-kacamata',
            'is_active' => true,
        ]);

        $lenses = Category::create([
            'name' => 'Lensa Kacamata',
            'slug' => 'lensa-kacamata',
            'is_active' => true,
        ]);

        $sourceFrame = Product::create([
            'category_id' => $frames->id,
            'name' => 'Source Frame',
            'slug' => 'source-frame',
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
            'name' => 'Best Similar Frame',
            'slug' => 'best-similar-frame',
            'brand' => 'Medio',
            'gender' => 'unisex',
            'frame_shape' => 'round',
            'frame_material' => 'acetate',
            'frame_color' => 'black',
            'face_size_fit' => 'medium',
            'price' => 110000,
            'stock' => 4,
            'weight' => 500,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $frames->id,
            'name' => 'Out Of Stock Similar Frame',
            'slug' => 'out-of-stock-similar-frame',
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
            'category_id' => $lenses->id,
            'name' => 'Lensa Blue Light',
            'slug' => 'lensa-blue-light',
            'brand' => 'Zeiss',
            'price' => 200000,
            'stock' => 8,
            'weight' => 100,
            'is_active' => true,
        ]);

        $lensOption = LensOption::create([
            'name' => 'Single Vision Premium',
            'type' => 'single_vision',
            'base_price' => 300000,
            'prescription_rules' => ['max_sphere' => '4.00'],
            'is_active' => true,
        ]);

        ProductCompatibility::create([
            'frame_product_id' => $sourceFrame->id,
            'lens_option_id' => $lensOption->id,
            'compatibility_rule' => ['fit' => 'standard'],
        ]);

        $this->getJson('/api/products/source-frame/recommendations')
            ->assertOk()
            ->assertJsonPath('similar_frames.0.slug', 'best-similar-frame')
            ->assertJsonPath('compatible_lenses.0.slug', 'lensa-blue-light')
            ->assertJsonPath('compatible_lens_options.0.name', 'Single Vision Premium')
            ->assertJsonMissing(['out-of-stock-similar-frame']);
    }
}
