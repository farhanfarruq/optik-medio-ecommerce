<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Koleksi Kacamata Mewah 2026',
                'subtitle' => 'Temukan gaya elegan dengan koleksi premium terbaru dari Optik Medio.',
                'image_path' => 'banners/luxury-banner.png',
                'cta_label' => 'Jelajahi Koleksi',
                'link_type' => 'external',
                'external_url' => '/products',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Summer Sunglasses Sale',
                'subtitle' => 'Tampil kece di bawah sinar matahari dengan diskon hingga 50%.',
                'image_path' => 'banners/summer-sale.png',
                'cta_label' => 'Ambil Diskon',
                'link_type' => 'external',
                'external_url' => '/promo',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Lensa Anti Radiasi Digital',
                'subtitle' => 'Lindungi mata Anda dari paparan sinar biru gadget dengan lensa khusus.',
                'image_path' => 'banners/blue-light.png',
                'cta_label' => 'Cek Produk',
                'link_type' => 'external',
                'external_url' => '/category/blue-light',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::updateOrCreate(
                ['title' => $banner['title']],
                $banner
            );
        }
    }
}
