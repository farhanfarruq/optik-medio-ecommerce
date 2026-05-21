<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductExpansionSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string}>
     */
    private array $categoryDefaults = [
        'frame-pria' => ['name' => 'Frame Pria', 'description' => 'Frame optik untuk pria dengan ukuran dan desain tegas.'],
        'frame-wanita' => ['name' => 'Frame Wanita', 'description' => 'Frame optik untuk wanita dengan pilihan bentuk ringan dan elegan.'],
        'frame-unisex' => ['name' => 'Frame Unisex', 'description' => 'Frame optik netral untuk lensa resep, kerja harian, dan gaya personal.'],
        'kacamata-minus' => ['name' => 'Kacamata Minus', 'description' => 'Pilihan frame siap dipasangkan dengan lensa minus sesuai resep.'],
        'kacamata-baca' => ['name' => 'Kacamata Baca', 'description' => 'Kacamata baca praktis untuk kebutuhan dekat dan aktivitas harian.'],
        'kacamata-hitam' => ['name' => 'Kacamata Hitam', 'description' => 'Kacamata hitam UV400 untuk aktivitas luar ruangan.'],
        'lensa-kacamata' => ['name' => 'Lensa Kacamata', 'description' => 'Lensa single vision, blue control, dan progressive.'],
        'softlens' => ['name' => 'Softlens', 'description' => 'Softlens harian, bulanan, clear, dan warna.'],
        'kacamata-anak' => ['name' => 'Kacamata Anak', 'description' => 'Frame aman, ringan, dan fleksibel untuk anak dan remaja.'],
        'aksesoris' => ['name' => 'Aksesoris', 'description' => 'Case, lap microfiber, strap, dan perlengkapan pendukung.'],
        'perawatan-kacamata' => ['name' => 'Perawatan Kacamata', 'description' => 'Cairan pembersih, anti-fog, dan paket perawatan lensa.'],
        'paket-pemeriksaan' => ['name' => 'Paket Pemeriksaan', 'description' => 'Paket pemeriksaan mata dan konsultasi optik.'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = $this->ensureCategories();

            foreach ($this->catalog() as $item) {
                $category = $categories[$item['category_slug']];
                $product = Product::withTrashed()->updateOrCreate(
                    ['slug' => $item['slug']],
                    $this->buildProductPayload($item, $category->id)
                );

                if ($product->trashed()) {
                    $product->restore();
                }

                $product->productImages()->delete();
            $product->productVariants()->delete();

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $this->placeholderImagePath($item),
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'is_active' => true,
                ]);

                foreach (array_values($item['variants'] ?? $this->defaultVariants($item)) as $index => $variant) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variant['sku'] ?? ($item['sku'] . '-V' . ($index + 1)),
                        'name' => $variant['name'] ?? 'Default',
                        'color' => $variant['color'] ?? ($item['frame_color'] ?? null),
                        'lens_size' => $variant['lens_size'] ?? null,
                        'stock' => (int) ($variant['stock'] ?? $item['stock']),
                        'price' => (float) ($variant['price'] ?? $item['price']),
                        'sort_order' => $index,
                        'is_default' => $index === 0,
                        'is_active' => true,
                        'attributes' => $variant['attributes'] ?? [],
                    ]);
                }
            }
        });

        $this->command?->info('Product expansion catalog seeded with placeholder visuals.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        /** @var array<int, array<string, mixed>> $catalog */
        $catalog = require database_path('seeders/data/product_expansion_catalog.php');

        return $catalog;
    }

    /**
     * @return array<string, Category>
     */
    private function ensureCategories(): array
    {
        $result = [];

        foreach ($this->categoryDefaults as $slug => $category) {
            $record = Category::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'meta_title' => $category['name'] . ' | Optik Medio',
                    'meta_description' => Str::limit($category['description'], 155, ''),
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }

            $result[$slug] = $record;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function buildProductPayload(array $item, int $categoryId): array
    {
        $imagePath = $this->placeholderImagePath($item);
        $type = (string) $item['type'];

        $requiresPrescription = in_array($type, ['frame', 'lens', 'softlens', 'kid_frame'], true);
        $notForSale = $type === 'service';
        $googleCategory = match ($type) {
            'frame', 'kid_frame' => 'Health & Beauty > Personal Care > Vision Care > Eyeglasses',
            'reader' => 'Health & Beauty > Personal Care > Vision Care > Reading Glasses',
            'sunglasses' => 'Apparel & Accessories > Clothing Accessories > Sunglasses',
            'lens' => 'Health & Beauty > Personal Care > Vision Care > Eyeglass Lenses',
            'softlens' => 'Health & Beauty > Personal Care > Vision Care > Contact Lenses',
            'accessory', 'care' => 'Health & Beauty > Personal Care > Vision Care > Eyewear Accessories',
            default => 'Health & Beauty > Personal Care > Vision Care',
        };

        $attributes = $item['attributes'] ?? [];
        $variants = $item['variants'] ?? $this->defaultVariants($item);
        $stock = (int) ($item['stock'] ?? 0);

        return [
            'category_id' => $categoryId,
            'name' => $item['name'],
            'slug' => $item['slug'],
            'meta_title' => Str::limit($item['name'] . ' | Optik Medio', 70, ''),
            'meta_description' => Str::limit((string) $item['description'], 155, ''),
            'canonical_slug' => $item['slug'],
            'og_image' => $imagePath,
            'sku' => $item['sku'],
            'description' => $item['description'],
            'brand' => $item['brand'],
            'gender' => $attributes['gender'] ?? null,
            'frame_shape' => $attributes['frame_shape'] ?? null,
            'frame_material' => $attributes['frame_material'] ?? null,
            'frame_color' => $item['frame_color'] ?? null,
            'face_size_fit' => $attributes['face_size_fit'] ?? null,
            'price' => $item['price'],
            'stock' => $stock,
            'low_stock_threshold' => max(3, min(8, (int) floor(max($stock, 6) / 3))),
            'weight' => $attributes['weight'] ?? 140,
            'dimensions' => [
                'lens_width' => $attributes['lens_width'] ?? null,
                'bridge_width' => $attributes['bridge_width'] ?? null,
                'temple_length' => $attributes['temple_length'] ?? null,
                'frame_width' => $attributes['frame_width'] ?? null,
            ],
            'lens_width' => $attributes['lens_width'] ?? null,
            'bridge_width' => $attributes['bridge_width'] ?? null,
            'temple_length' => $attributes['temple_length'] ?? null,
            'frame_width' => $attributes['frame_width'] ?? null,
            'variants' => $variants,
            'images' => [$imagePath],
            'tags' => $item['tags'] ?? [],
            'campaign_tags' => $item['campaign_tags'] ?? [],
            'google_product_category' => $item['google_product_category'] ?? $googleCategory,
            'gtin' => null,
            'mpn' => $item['sku'],
            'condition' => 'new',
            'is_active' => true,
            'is_best_seller' => (bool) ($item['is_best_seller'] ?? false),
            'is_featured' => (bool) ($item['is_featured'] ?? false),
            'recommendation_priority' => (int) ($item['recommendation_priority'] ?? 50),
            'is_new' => (bool) ($item['is_new'] ?? false),
            'is_not_for_sale' => $notForSale,
            'is_prescription_required' => $requiresPrescription,
            'prescription_rules' => $requiresPrescription
                ? ['requires_optical_validation' => true, 'type' => $type]
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<int, array<string, mixed>>
     */
    private function defaultVariants(array $item): array
    {
        return [[
            'sku' => $item['sku'] . '-STD',
            'name' => 'Default',
            'color' => $item['frame_color'] ?? 'Default',
            'lens_size' => isset($item['attributes']['lens_width']) ? (string) $item['attributes']['lens_width'] : 'Standard',
            'stock' => (int) $item['stock'],
            'price' => (float) $item['price'],
            'attributes' => ['source' => 'product-expansion-default'],
        ]];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function placeholderImagePath(array $item): string
    {
        $relativePath = 'products/seed-expansion/' . $item['slug'] . '.svg';
        $absolutePath = storage_path('app/public/' . $relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $this->squareSvg(
            (string) $item['name'],
            (string) ($item['color_hex'] ?? '#7c6f64')
        ));

        return $relativePath;
    }

    private function squareSvg(string $label, string $accent): string
    {
        $safeLabel = htmlspecialchars(Str::limit($label, 38, ''), ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200">
  <rect width="1200" height="1200" fill="#f7f1e7"/>
  <rect x="90" y="90" width="1020" height="1020" rx="40" fill="#fffdf8" stroke="#e7dcc6" stroke-width="6"/>
  <circle cx="415" cy="540" r="150" fill="none" stroke="{$accent}" stroke-width="30"/>
  <circle cx="785" cy="540" r="150" fill="none" stroke="{$accent}" stroke-width="30"/>
  <path d="M565 540h70" fill="none" stroke="{$accent}" stroke-width="30" stroke-linecap="round"/>
  <path d="M310 435c70-84 152-126 246-126h88c94 0 176 42 246 126" fill="none" stroke="{$accent}" stroke-width="30" stroke-linecap="round"/>
  <text x="600" y="930" text-anchor="middle" font-family="Arial, sans-serif" font-size="44" font-weight="700" fill="#1f1b17">{$safeLabel}</text>
  <text x="600" y="990" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" fill="#6c6257">AI placeholder 1:1 - replace with generated PNG</text>
</svg>
SVG;
    }
}
