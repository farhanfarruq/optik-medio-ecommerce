<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->resetCatalog();
            $categories = $this->seedCategories();
            $this->seedProducts($categories);
        });

        $this->command?->info('Katalog produk berhasil di-reset dan diisi ulang.');
    }

    private function resetCatalog(): void
    {
        foreach ([
            'cart_items',
            'wishlists',
            'product_reviews',
            'product_images',
            'product_variants',
            'product_compatibilities',
            'promo_buy_product',
            'promo_discount_product',
            'stock_adjustments',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('promos')) {
            DB::table('promos')->update([
                'buy_product_id' => null,
                'get_product_id' => null,
                'discount_product_id' => null,
            ]);
        }

        if (Schema::hasTable('banners') && Schema::hasColumn('banners', 'product_id')) {
            DB::table('banners')->update(['product_id' => null]);
        }

        $lockedProductIds = Schema::hasTable('order_items')
            ? DB::table('order_items')
                ->whereNotNull('product_id')
                ->distinct()
                ->pluck('product_id')
                ->all()
            : [];

        Product::withTrashed()
            ->whereIn('id', $lockedProductIds)
            ->get()
            ->each(function (Product $product): void {
                $name = Str::startsWith($product->name, '[Arsip] ') ? $product->name : '[Arsip] ' . $product->name;
                $sku = $product->sku && Str::startsWith($product->sku, 'ARSIP-')
                    ? $product->sku
                    : ($product->sku ? 'ARSIP-' . $product->id . '-' . $product->sku : 'ARSIP-' . $product->id);

                $product->forceFill([
                    'name' => $name,
                    'slug' => 'arsip-' . $product->id . '-' . Str::slug(Str::replaceFirst('[Arsip] ', '', $product->name)),
                    'sku' => $sku,
                    'is_active' => false,
                    'is_featured' => false,
                    'is_best_seller' => false,
                    'is_new' => false,
                ])->save();

                if (! $product->trashed()) {
                    $product->delete();
                }
            });

        Product::withTrashed()
            ->when($lockedProductIds !== [], fn ($query) => $query->whereNotIn('id', $lockedProductIds))
            ->get()
            ->each(fn (Product $product) => $product->forceDelete());
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $items = [
            ['Frame Pria', 'frame-pria', 'Frame optik untuk pria dengan ukuran dan desain tegas.'],
            ['Frame Wanita', 'frame-wanita', 'Frame optik untuk wanita dengan pilihan bentuk ringan dan elegan.'],
            ['Frame Unisex', 'frame-unisex', 'Frame optik netral untuk lensa resep, kerja harian, dan gaya personal.'],
            ['Kacamata Minus', 'kacamata-minus', 'Pilihan frame siap dipasangkan dengan lensa minus sesuai resep.'],
            ['Kacamata Baca', 'kacamata-baca', 'Kacamata baca praktis untuk kebutuhan dekat dan aktivitas harian.'],
            ['Kacamata Hitam', 'kacamata-hitam', 'Kacamata hitam UV400 untuk aktivitas luar ruangan.'],
            ['Lensa Kacamata', 'lensa-kacamata', 'Lensa single vision, blue control, dan progressive.'],
            ['Softlens', 'softlens', 'Softlens harian, bulanan, clear, dan warna.'],
            ['Kacamata Anak', 'kacamata-anak', 'Frame aman, ringan, dan fleksibel untuk anak dan remaja.'],
            ['Aksesoris', 'aksesoris', 'Case, lap microfiber, strap, dan perlengkapan pendukung.'],
            ['Perawatan Kacamata', 'perawatan-kacamata', 'Cairan pembersih, anti-fog, dan paket perawatan lensa.'],
            ['Paket Pemeriksaan', 'paket-pemeriksaan', 'Paket pemeriksaan mata dan konsultasi optik.'],
        ];

        $categories = [];
        $activeSlugs = array_column($items, 1);

        foreach ($items as [$name, $slug, $description]) {
            $category = Category::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'image' => $this->writeCategorySvg($slug, $name),
                    'meta_title' => $name . ' | Optik Medio',
                    'meta_description' => Str::limit($description, 155, ''),
                    'og_image' => null,
                    'is_active' => true,
                ]
            );

            if ($category->trashed()) {
                $category->restore();
            }

            $categories[$slug] = $category;
        }

        Category::query()
            ->whereNotIn('slug', $activeSlugs)
            ->whereDoesntHave('products', fn ($query) => $query->where('is_active', true))
            ->get()
            ->each(function (Category $category): void {
                $category->update(['is_active' => false]);
                $category->delete();
            });

        return $categories;
    }

    /**
     * @param array<string, Category> $categories
     */
    private function seedProducts(array $categories): void
    {
        foreach ($this->products() as $index => $item) {
            $category = $categories[$item['category_slug']];
            $imagePath = $this->writeProductImage($item['slug'], $item['name'], $item['color']);
            $variants = $this->normalizeVariants($item, $item['variants'] ?? $this->defaultVariants($item));

            $product = Product::create([
                'category_id' => $category->id,
                'name' => $item['name'],
                'slug' => $item['slug'],
                'meta_title' => Str::limit($item['name'] . ' | Optik Medio', 70, ''),
                'meta_description' => Str::limit($item['description'], 155, ''),
                'canonical_slug' => $item['slug'],
                'og_image' => $imagePath,
                'sku' => $item['sku'],
                'description' => $item['description'],
                'brand' => $item['brand'],
                'gender' => $item['gender'],
                'frame_shape' => $item['frame_shape'],
                'frame_material' => $item['frame_material'],
                'frame_color' => $item['frame_color'],
                'face_size_fit' => $item['face_size_fit'],
                'price' => $item['price'],
                'stock' => $item['stock'],
                'low_stock_threshold' => $item['low_stock_threshold'],
                'weight' => $item['weight'],
                'dimensions' => $item['dimensions'],
                'lens_width' => $item['lens_width'],
                'bridge_width' => $item['bridge_width'],
                'temple_length' => $item['temple_length'],
                'frame_width' => $item['frame_width'],
                'variants' => $variants,
                'images' => [$imagePath],
                'tags' => $item['tags'],
                'campaign_tags' => $item['campaign_tags'],
                'google_product_category' => $item['google_product_category'],
                'gtin' => $item['gtin'],
                'mpn' => $item['mpn'],
                'condition' => 'new',
                'is_active' => true,
                'is_best_seller' => $item['is_best_seller'],
                'is_featured' => $item['is_featured'],
                'recommendation_priority' => $item['recommendation_priority'],
                'is_new' => $item['is_new'],
                'is_not_for_sale' => $item['is_not_for_sale'],
                'is_prescription_required' => $item['is_prescription_required'],
                'prescription_rules' => $item['prescription_rules'],
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
                'is_active' => true,
            ]);

            foreach ($variants as $variantIndex => $variant) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variant['sku'],
                    'name' => $variant['name'],
                    'color' => $variant['color'],
                    'lens_size' => $variant['lens_size'],
                    'stock' => $variant['stock'],
                    'price' => $variant['price'],
                    'sort_order' => $variantIndex,
                    'is_default' => $variantIndex === 0,
                    'is_active' => true,
                    'attributes' => $variant['attributes'],
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            $this->frame('Frame Unisex', 'frame-unisex', 'MED-FRM-001', 'medio-classic-round-tr90', 'Medio Classic Round TR90', 'Medio', 'unisex', 'round', 'tr90', 'Matte Black', 'medium', 52, 18, 145, 138, 495000, 18, true, true, false, 94, ['classic', 'daily', 'lightweight'], 'Frame bulat TR90 yang ringan, fleksibel, dan nyaman untuk pemakaian kerja maupun kuliah. Cocok dipasangkan dengan lensa minus, blue control, atau lensa tipis indeks tinggi.', '#1f2937'),
            $this->frame('Kacamata Minus', 'kacamata-minus', 'MED-MIN-002', 'medio-workflex-rectangle', 'Medio WorkFlex Rectangle', 'Medio', 'unisex', 'rectangle', 'ultem', 'Dark Brown', 'medium', 54, 17, 145, 140, 545000, 22, true, true, true, 91, ['office', 'minus-ready', 'durable'], 'Frame rectangle berbahan Ultem untuk pengguna lensa minus yang membutuhkan kacamata stabil, ringan, dan tidak cepat membuat area hidung lelah.', '#5b4636'),
            $this->frame('Frame Unisex', 'frame-unisex', 'RBN-FRM-5154', 'ray-ban-rb5154-clubmaster-classic-seed', 'Ray-Ban RB5154 Clubmaster Classic', 'Ray-Ban', 'unisex', 'browline', 'acetate', 'Black Gold', 'medium', 51, 21, 145, 140, 1850000, 10, true, true, false, 89, ['premium', 'browline', 'signature'], 'Frame browline ikonik dari Ray-Ban dengan kombinasi acetate dan metal. Pilihan premium untuk tampilan formal, semi-formal, dan koleksi harian.', '#111827'),
            $this->frame('Frame Wanita', 'frame-wanita', 'MED-WMN-004', 'medio-soft-cat-eye-rose', 'Medio Soft Cat Eye Rose', 'Medio', 'women', 'cat_eye', 'acetate', 'Rose Brown', 'small', 51, 17, 140, 132, 525000, 14, false, true, true, 85, ['women', 'cat-eye', 'soft-look'], 'Frame cat eye ringan dengan warna rose brown yang lembut. Cocok untuk pengguna yang ingin tampilan elegan tanpa mengorbankan kenyamanan lensa resep harian.', '#9f5f63'),
            $this->frame('Frame Pria', 'frame-pria', 'OAK-FRM-8046', 'oakley-ox8046-crosslink-zero-seed', 'Oakley OX8046 Crosslink Zero', 'Oakley', 'men', 'rectangle', 'tr90', 'Matte Blue', 'large', 56, 17, 143, 145, 2350000, 8, false, true, true, 82, ['sport', 'active', 'impact-resistant'], 'Frame sport Oakley dengan desain ringan dan grip stabil untuk aktivitas aktif. Cocok untuk pengguna yang banyak bergerak dan tetap butuh koreksi resep.', '#1d4ed8'),
            $this->frame('Kacamata Anak', 'kacamata-anak', 'MED-KID-005', 'medio-kids-flex-safe', 'Medio Kids Flex Safe', 'Medio Kids', 'kids', 'oval', 'tr90', 'Sky Blue', 'small', 46, 16, 130, 122, 385000, 16, false, true, true, 88, ['kids', 'flexible', 'safe'], 'Frame anak berbahan fleksibel dengan engsel lentur, bobot ringan, dan nosepad nyaman. Dirancang untuk aktivitas sekolah dan penggunaan harian.', '#38bdf8'),
            $this->frame('Kacamata Hitam', 'kacamata-hitam', 'MED-SUN-006', 'medio-uv400-wayfarer', 'Medio UV400 Wayfarer', 'Medio Sun', 'unisex', 'wayfarer', 'acetate', 'Glossy Black', 'medium', 53, 19, 145, 141, 425000, 20, true, true, true, 87, ['uv400', 'outdoor', 'sunglasses'], 'Kacamata hitam Wayfarer dengan perlindungan UV400 dan lensa gelap nyaman untuk berkendara, liburan, serta aktivitas luar ruangan.', '#0f172a'),
            $this->frame('Kacamata Hitam', 'kacamata-hitam', 'RBN-SUN-3025', 'ray-ban-rb3025-aviator-classic-seed', 'Ray-Ban RB3025 Aviator Classic', 'Ray-Ban', 'unisex', 'aviator', 'metal', 'Gold', 'medium', 58, 14, 135, 140, 2100000, 12, true, true, false, 86, ['aviator', 'uv-protection', 'premium'], 'Sunglasses aviator klasik dengan karakter timeless dan perlindungan UV. Cocok untuk koleksi premium dan penggunaan outdoor yang tetap rapi.', '#b45309'),
            $this->frame('Kacamata Hitam', 'kacamata-hitam', 'OAK-SUN-9102', 'oakley-holbrook-polarized-seed', 'Oakley Holbrook Polarized', 'Oakley', 'men', 'square', 'tr90', 'Matte Black', 'large', 57, 18, 137, 143, 1950000, 7, false, true, false, 80, ['polarized', 'sport', 'outdoor'], 'Sunglasses polarized dengan tampilan tegas dan proteksi glare untuk berkendara maupun aktivitas outdoor. Frame ringan menjaga kenyamanan pemakaian lama.', '#111827'),
            $this->lens('Lensa Kacamata', 'lensa-kacamata', 'ESS-LEN-001', 'essilor-crizal-blue-uv-single-vision', 'Essilor Crizal Blue UV Single Vision', 'Essilor', 1250000, 30, true, true, 92, ['blue-control', 'anti-reflective', 'single-vision'], 'Lensa single vision dengan perlindungan blue-violet dan lapisan anti-reflective. Cocok untuk pengguna komputer, kerja kantor, dan aktivitas digital harian.', '#2563eb'),
            $this->lens('Lensa Kacamata', 'lensa-kacamata', 'HOY-LEN-002', 'hoya-hilux-anti-reflective', 'Hoya Hilux Anti-Reflective', 'Hoya', 850000, 28, false, true, 78, ['clear-lens', 'anti-reflective', 'daily'], 'Lensa jernih anti-reflective untuk pemakaian harian dengan hasil pandang natural. Pilihan efisien untuk resep minus, plus, atau silinder ringan.', '#64748b'),
            $this->lens('Lensa Kacamata', 'lensa-kacamata', 'MED-LEN-003', 'medio-progressive-daily-comfort', 'Medio Progressive Daily Comfort', 'Medio Lens', 1750000, 14, false, true, 84, ['progressive', 'presbyopia', 'comfort'], 'Lensa progressive untuk kebutuhan jarak dekat, menengah, dan jauh dalam satu lensa. Dirancang untuk transisi pandang yang halus dan nyaman.', '#0f766e'),
            $this->contactLens('Softlens', 'softlens', 'ACV-CON-001', 'acuvue-moist-1-day-clear', 'Acuvue Moist 1 Day Clear', 'Acuvue', 315000, 40, true, true, 83, ['daily-contact-lens', 'clear', 'moist'], 'Softlens harian bening dengan kelembapan nyaman untuk pemakaian singkat sampai aktivitas seharian. Praktis karena tidak perlu cairan perawatan.', '#60a5fa'),
            $this->contactLens('Softlens', 'softlens', 'BIO-CON-002', 'biofinity-monthly-clear', 'Biofinity Monthly Clear', 'CooperVision', 465000, 24, false, true, 79, ['monthly-contact-lens', 'clear', 'silicone-hydrogel'], 'Softlens bulanan bening berbahan silicone hydrogel dengan suplai oksigen baik. Cocok untuk pengguna rutin yang membutuhkan kenyamanan stabil.', '#22c55e'),
            $this->contactLens('Softlens', 'softlens', 'FRL-CON-003', 'freshlook-color-blend-hazel', 'FreshLook Color Blend Hazel', 'FreshLook', 275000, 18, false, true, 70, ['colored-contact-lens', 'hazel', 'monthly'], 'Softlens warna hazel dengan efek natural untuk mengubah tampilan mata tanpa terlihat berlebihan. Tetap membutuhkan resep dan ukuran yang sesuai.', '#a16207'),
            $this->reader('Kacamata Baca', 'kacamata-baca', 'MED-REA-100', 'medio-reader-slim-plus-100', 'Medio Reader Slim +1.00', 'Medio Reader', '+1.00', 165000, 26, true, true, 72, '#334155'),
            $this->reader('Kacamata Baca', 'kacamata-baca', 'MED-REA-200', 'medio-reader-clip-on-plus-200', 'Medio Reader Clip On +2.00', 'Medio Reader', '+2.00', 185000, 22, false, true, 68, '#713f12'),
            $this->accessory('Aksesoris', 'aksesoris', 'MED-ACC-001', 'medio-premium-hardcase', 'Medio Premium Hardcase', 'Medio Care', 95000, 50, true, false, 62, ['case', 'protection', 'travel'], 'Hardcase kacamata dengan struktur kokoh dan lapisan dalam halus untuk melindungi frame dari tekanan, goresan, dan benturan saat dibawa bepergian.', '#374151'),
            $this->accessory('Aksesoris', 'aksesoris', 'MED-ACC-002', 'medio-microfiber-cleaning-kit', 'Medio Microfiber Cleaning Kit', 'Medio Care', 75000, 60, false, false, 58, ['microfiber', 'cleaning', 'care'], 'Paket lap microfiber lembut untuk membersihkan lensa tanpa meninggalkan serat kasar. Cocok untuk kacamata resep, sunglasses, dan lensa kamera kecil.', '#7c3aed'),
            $this->accessory('Perawatan Kacamata', 'perawatan-kacamata', 'MED-CAR-001', 'medio-lens-cleaning-spray', 'Medio Lens Cleaning Spray', 'Medio Care', 55000, 75, true, false, 60, ['spray', 'lens-cleaner', 'daily-care'], 'Cairan pembersih lensa untuk mengangkat minyak, debu, dan noda ringan tanpa merusak coating. Aman digunakan bersama lap microfiber bersih.', '#0ea5e9'),
            $this->accessory('Perawatan Kacamata', 'perawatan-kacamata', 'MED-CAR-002', 'medio-anti-fog-wipes', 'Medio Anti Fog Wipes', 'Medio Care', 68000, 45, false, false, 55, ['anti-fog', 'wipes', 'mask-friendly'], 'Tisu anti-fog sekali pakai untuk membantu mengurangi embun pada lensa saat memakai masker, helm, atau berpindah dari ruang dingin ke hangat.', '#14b8a6'),
            $this->service('Paket Pemeriksaan', 'paket-pemeriksaan', 'MED-SVC-001', 'paket-pemeriksaan-mata-lengkap', 'Paket Pemeriksaan Mata Lengkap', 'Optik Medio', 100000, 999, true, false, 66, ['eye-test', 'consultation', 'appointment'], 'Paket pemeriksaan mata lengkap untuk membaca ukuran lensa, konsultasi kebutuhan harian, dan rekomendasi frame atau lensa yang sesuai.', '#c19a51'),
        ];
    }

    private function frame(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, string $gender, string $shape, string $material, string $colorName, string $fit, int $lensWidth, int $bridgeWidth, int $templeLength, int $frameWidth, int $price, int $stock, bool $bestSeller, bool $featured, bool $isNew, int $priority, array $tags, string $description, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, $isNew, false, true, $priority, $tags, $description, $color, [
            'gender' => $gender,
            'frame_shape' => $shape,
            'frame_material' => $material,
            'frame_color' => $colorName,
            'face_size_fit' => $fit,
            'lens_width' => $lensWidth,
            'bridge_width' => $bridgeWidth,
            'temple_length' => $templeLength,
            'frame_width' => $frameWidth,
            'weight' => 180,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care > Eyeglasses',
        ]);
    }

    private function lens(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, int $price, int $stock, bool $bestSeller, bool $featured, int $priority, array $tags, string $description, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, false, false, true, $priority, $tags, $description, $color, [
            'weight' => 80,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care > Eyeglass Lenses',
            'variants' => [
                ['name' => 'Index 1.56', 'color' => 'Clear', 'lens_size' => 'Standard', 'stock' => $stock, 'price' => $price, 'attributes' => ['index' => '1.56', 'coating' => 'anti-reflective']],
                ['name' => 'Index 1.61', 'color' => 'Clear', 'lens_size' => 'Thin', 'stock' => max(4, (int) floor($stock / 2)), 'price' => $price + 350000, 'attributes' => ['index' => '1.61', 'coating' => 'anti-reflective']],
            ],
        ]);
    }

    private function contactLens(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, int $price, int $stock, bool $bestSeller, bool $featured, int $priority, array $tags, string $description, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, false, false, true, $priority, $tags, $description, $color, [
            'weight' => 120,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care > Contact Lenses',
            'variants' => [
                ['name' => '-1.00', 'color' => 'Clear', 'lens_size' => '14.0', 'stock' => max(3, (int) floor($stock / 3)), 'price' => $price, 'attributes' => ['sphere' => '-1.00']],
                ['name' => '-2.00', 'color' => 'Clear', 'lens_size' => '14.0', 'stock' => max(3, (int) floor($stock / 3)), 'price' => $price, 'attributes' => ['sphere' => '-2.00']],
                ['name' => '-3.00', 'color' => 'Clear', 'lens_size' => '14.0', 'stock' => max(3, (int) floor($stock / 3)), 'price' => $price, 'attributes' => ['sphere' => '-3.00']],
            ],
        ]);
    }

    private function reader(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, string $power, int $price, int $stock, bool $bestSeller, bool $featured, int $priority, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, true, false, false, $priority, ['reader', 'presbyopia', 'ready-stock'], 'Kacamata baca siap pakai dengan power ' . $power . ' untuk membaca buku, layar ponsel, dan aktivitas dekat. Frame ringan membuat pemakaian harian terasa praktis.', $color, [
            'gender' => 'unisex',
            'frame_shape' => 'rectangle',
            'frame_material' => 'tr90',
            'frame_color' => 'Smoke',
            'face_size_fit' => 'medium',
            'lens_width' => 50,
            'bridge_width' => 18,
            'temple_length' => 140,
            'frame_width' => 136,
            'weight' => 150,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care > Reading Glasses',
            'variants' => [
                ['name' => $power, 'color' => 'Smoke', 'lens_size' => 'Medium', 'stock' => $stock, 'price' => $price, 'attributes' => ['power' => $power]],
            ],
        ]);
    }

    private function accessory(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, int $price, int $stock, bool $bestSeller, bool $featured, int $priority, array $tags, string $description, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, false, false, false, $priority, $tags, $description, $color, [
            'weight' => 120,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care > Eyewear Accessories',
            'prescription_rules' => [],
        ]);
    }

    private function service(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, int $price, int $stock, bool $bestSeller, bool $featured, int $priority, array $tags, string $description, string $color): array
    {
        return $this->base($category, $categorySlug, $sku, $slug, $name, $brand, $price, $stock, $bestSeller, $featured, false, true, false, $priority, $tags, $description, $color, [
            'weight' => 1,
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care',
            'prescription_rules' => [],
        ]);
    }

    private function base(string $category, string $categorySlug, string $sku, string $slug, string $name, string $brand, int $price, int $stock, bool $bestSeller, bool $featured, bool $isNew, bool $notForSale, bool $requiresPrescription, int $priority, array $tags, string $description, string $color, array $overrides = []): array
    {
        $base = [
            'category' => $category,
            'category_slug' => $categorySlug,
            'sku' => $sku,
            'slug' => $slug,
            'name' => $name,
            'brand' => $brand,
            'gender' => null,
            'frame_shape' => null,
            'frame_material' => null,
            'frame_color' => null,
            'face_size_fit' => null,
            'lens_width' => null,
            'bridge_width' => null,
            'temple_length' => null,
            'frame_width' => null,
            'price' => $price,
            'stock' => $stock,
            'low_stock_threshold' => max(2, (int) ceil($stock * 0.15)),
            'weight' => 250,
            'dimensions' => ['panjang' => 18, 'lebar' => 8, 'tinggi' => 6],
            'tags' => $tags,
            'campaign_tags' => array_values(array_unique([$categorySlug, $featured ? 'featured' : 'regular', $bestSeller ? 'best-seller' : 'catalog'])),
            'google_product_category' => 'Health & Beauty > Personal Care > Vision Care',
            'gtin' => null,
            'mpn' => $sku,
            'is_best_seller' => $bestSeller,
            'is_featured' => $featured,
            'recommendation_priority' => $priority,
            'is_new' => $isNew,
            'is_not_for_sale' => $notForSale,
            'is_prescription_required' => $requiresPrescription,
            'prescription_rules' => $requiresPrescription ? ['min_sphere' => '-12.00', 'max_sphere' => '+8.00', 'max_cylinder' => '-4.00', 'pd_required' => 'true'] : [],
            'description' => $description,
            'color' => $color,
        ];

        return array_replace($base, $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultVariants(array $item): array
    {
        return [
            [
                'sku' => $item['sku'] . '-STD',
                'name' => 'Standard',
                'color' => $item['frame_color'] ?: 'Default',
                'lens_size' => $item['lens_width'] ? $item['lens_width'] . '-' . $item['bridge_width'] : 'Default',
                'stock' => $item['stock'],
                'price' => $item['price'],
                'attributes' => [
                    'fit' => $item['face_size_fit'] ?: 'default',
                    'material' => $item['frame_material'] ?: 'default',
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $variants
     * @return array<int, array<string, mixed>>
     */
    private function normalizeVariants(array $item, array $variants): array
    {
        return array_map(
            fn (array $variant, int $index): array => [
                'sku' => $variant['sku'] ?? $item['sku'] . '-V' . ($index + 1),
                'name' => $variant['name'] ?? 'Standard',
                'color' => $variant['color'] ?? ($item['frame_color'] ?: 'Default'),
                'lens_size' => $variant['lens_size'] ?? 'Default',
                'stock' => $variant['stock'] ?? $item['stock'],
                'price' => $variant['price'] ?? $item['price'],
                'attributes' => $variant['attributes'] ?? [],
            ],
            $variants,
            array_keys($variants),
        );
    }

    private function writeCategorySvg(string $slug, string $name): string
    {
        return $this->writeSvg("categories/seed/{$slug}.svg", $name, '#c19a51');
    }

    private function writeProductImage(string $slug, string $name, string $color): string
    {
        $sourcePath = public_path('images/foto_produk/' . $this->productPhotoFileName($name));

        if (! File::exists($sourcePath)) {
            return $this->writeProductSvg($slug, $name, $color);
        }

        $relativePath = "products/foto_produk/{$slug}." . pathinfo($sourcePath, PATHINFO_EXTENSION);
        $targetPath = storage_path('app/public/' . $relativePath);

        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($sourcePath, $targetPath);

        return $relativePath;
    }

    private function productPhotoFileName(string $name): string
    {
        return match ($name) {
            'Acuvue Moist 1 Day Clear' => 'Acuve Contact lenses.png',
            default => $name . '.png',
        };
    }

    private function writeProductSvg(string $slug, string $name, string $color): string
    {
        return $this->writeSvg("products/seed/{$slug}.svg", $name, $color);
    }

    private function writeSvg(string $relativePath, string $label, string $color): string
    {
        $path = storage_path('app/public/' . $relativePath);
        File::ensureDirectoryExists(dirname($path));

        $safeLabel = htmlspecialchars(Str::limit($label, 32, ''), ENT_QUOTES, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900">
  <rect width="1200" height="900" fill="#f7f2e8"/>
  <rect x="96" y="96" width="1008" height="708" rx="32" fill="#fffdf7" stroke="#e3d3ae" stroke-width="6"/>
  <path d="M260 455c52-64 128-96 228-96h224c100 0 176 32 228 96" fill="none" stroke="{$color}" stroke-width="28" stroke-linecap="round"/>
  <circle cx="394" cy="470" r="116" fill="none" stroke="{$color}" stroke-width="28"/>
  <circle cx="806" cy="470" r="116" fill="none" stroke="{$color}" stroke-width="28"/>
  <path d="M510 470h180" fill="none" stroke="{$color}" stroke-width="28" stroke-linecap="round"/>
  <text x="600" y="720" text-anchor="middle" font-family="Arial, sans-serif" font-size="42" font-weight="700" fill="#1a1209">{$safeLabel}</text>
</svg>
SVG;

        File::put($path, $svg);

        return $relativePath;
    }
}
