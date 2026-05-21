<?php

namespace Database\Seeders;

use App\Models\LensCoating;
use App\Models\LensOption;
use App\Models\Product;
use App\Models\ProductCompatibility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OpticalConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            ProductCompatibility::query()->delete();

            $lensOptions = $this->seedLensOptions();
            $this->seedLensCoatings();
            $this->seedProductCompatibilities($lensOptions);
        });

        $this->command?->info('Konfigurasi lensa, coating, dan kompatibilitas produk berhasil diisi.');
    }

    /**
     * @return array<string, LensOption>
     */
    private function seedLensOptions(): array
    {
        $items = [
            [
                'key' => 'single-basic',
                'name' => 'Single Vision Basic 1.56',
                'type' => 'single_vision',
                'base_price' => 150000,
                'prescription_rules' => ['min_sphere' => '-6.00', 'max_sphere' => '+4.00', 'min_cylinder' => '-2.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'single-blue',
                'name' => 'Single Vision Blue Control 1.56',
                'type' => 'blue_light',
                'base_price' => 300000,
                'prescription_rules' => ['min_sphere' => '-6.00', 'max_sphere' => '+4.00', 'min_cylinder' => '-2.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'high-index-161',
                'name' => 'High Index Thin 1.61',
                'type' => 'high_index',
                'base_price' => 550000,
                'prescription_rules' => ['min_sphere' => '-10.00', 'max_sphere' => '+6.00', 'min_cylinder' => '-4.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'high-index-167',
                'name' => 'High Index Ultra Thin 1.67',
                'type' => 'high_index',
                'base_price' => 950000,
                'prescription_rules' => ['min_sphere' => '-12.00', 'max_sphere' => '+8.00', 'min_cylinder' => '-4.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'progressive-daily',
                'name' => 'Progressive Daily Comfort',
                'type' => 'progressive',
                'base_price' => 1250000,
                'prescription_rules' => ['min_sphere' => '-8.00', 'max_sphere' => '+6.00', 'min_cylinder' => '-3.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'reading',
                'name' => 'Reading Lens Near Focus',
                'type' => 'reading',
                'base_price' => 180000,
                'prescription_rules' => ['min_sphere' => '+0.50', 'max_sphere' => '+4.00', 'min_cylinder' => '-1.50', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'photochromic',
                'name' => 'Photochromic UV Adaptive',
                'type' => 'photochromic',
                'base_price' => 650000,
                'prescription_rules' => ['min_sphere' => '-8.00', 'max_sphere' => '+5.00', 'min_cylinder' => '-3.00', 'max_cylinder' => '0.00'],
            ],
            [
                'key' => 'office-anti-radiation',
                'name' => 'Office Anti Radiation',
                'type' => 'anti_radiation',
                'base_price' => 425000,
                'prescription_rules' => ['min_sphere' => '-6.00', 'max_sphere' => '+4.00', 'min_cylinder' => '-2.50', 'max_cylinder' => '0.00'],
            ],
        ];

        $options = [];

        foreach ($items as $item) {
            $options[$item['key']] = LensOption::updateOrCreate(
                ['name' => $item['name']],
                [
                    'type' => $item['type'],
                    'base_price' => $item['base_price'],
                    'prescription_rules' => $item['prescription_rules'],
                    'is_active' => true,
                ],
            );
        }

        LensOption::query()
            ->whereNotIn('name', array_column($items, 'name'))
            ->update(['is_active' => false]);

        return $options;
    }

    private function seedLensCoatings(): void
    {
        $items = [
            ['Hard Multi Coating', 125000, 'Lapisan dasar untuk membantu mengurangi goresan ringan dan pantulan pada lensa harian.'],
            ['Blue Control Coating', 250000, 'Coating untuk membantu kenyamanan visual saat bekerja lama di depan layar digital.'],
            ['UV Protect Coating', 180000, 'Perlindungan tambahan terhadap paparan UV untuk aktivitas indoor dan outdoor ringan.'],
            ['Photochromic Coating', 375000, 'Lapisan adaptif yang membantu lensa menggelap saat terkena cahaya matahari.'],
            ['Premium Anti Glare', 325000, 'Coating premium untuk mengurangi silau dan menjaga tampilan lensa lebih jernih.'],
            ['Anti Fog Treatment', 95000, 'Treatment tambahan untuk membantu mengurangi embun pada kondisi tertentu.'],
        ];

        foreach ($items as [$name, $price, $description]) {
            LensCoating::updateOrCreate(
                ['name' => $name],
                [
                    'price' => $price,
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }

        LensCoating::query()
            ->whereNotIn('name', array_column($items, 0))
            ->update(['is_active' => false]);
    }

    /**
     * @param array<string, LensOption> $lensOptions
     */
    private function seedProductCompatibilities(array $lensOptions): void
    {
        $frameProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_prescription_required', true)
            ->whereNotIn('category_id', function ($query): void {
                $query->select('id')
                    ->from('categories')
                    ->whereIn('slug', ['lensa-kacamata', 'softlens', 'aksesoris', 'perawatan-kacamata', 'paket-pemeriksaan']);
            })
            ->get();

        foreach ($frameProducts as $frame) {
            foreach ($this->compatibleOptionKeys($frame) as $key) {
                if (! isset($lensOptions[$key])) {
                    continue;
                }

                ProductCompatibility::updateOrCreate(
                    [
                        'frame_product_id' => $frame->id,
                        'lens_option_id' => $lensOptions[$key]->id,
                    ],
                    [
                        'compatibility_rule' => [
                            'max_lens_width' => (string) ($frame->lens_width ?: 56),
                            'max_frame_width' => (string) ($frame->frame_width ?: 145),
                            'fit' => $frame->face_size_fit ?: 'medium',
                            'note' => 'Seeder compatibility untuk inspeksi admin.',
                        ],
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function compatibleOptionKeys(Product $frame): array
    {
        if ($frame->gender === 'kids') {
            return ['single-basic', 'single-blue', 'office-anti-radiation'];
        }

        if ($frame->category?->slug === 'kacamata-hitam') {
            return ['single-basic', 'photochromic', 'high-index-161'];
        }

        return [
            'single-basic',
            'single-blue',
            'high-index-161',
            'high-index-167',
            'progressive-daily',
            'reading',
            'photochromic',
            'office-anti-radiation',
        ];
    }
}
