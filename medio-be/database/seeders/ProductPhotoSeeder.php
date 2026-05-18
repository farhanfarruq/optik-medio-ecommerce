<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductPhotoSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $photos = [
        'Acuvue Moist 1 Day Clear' => 'Acuve Contact lenses.png',
        'Biofinity Monthly Clear' => 'Biofinity Monthly Clear.png',
        'Essilor Crizal Blue UV Single Vision' => 'Essilor Crizal Blue UV Single Vision.png',
        'FreshLook Color Blend Hazel' => 'FreshLook Color Blend Hazel.png',
        'Hoya Hilux Anti-Reflective' => 'Hoya Hilux Anti-Reflective.png',
        'Medio Anti Fog Wipes' => 'Medio Anti Fog Wipes.png',
        'Medio Classic Round TR90' => 'Medio Classic Round TR90.png',
        'Medio Kids Flex Safe' => 'Medio Kids Flex Safe.png',
        'Medio Lens Cleaning Spray' => 'Medio Lens Cleaning Spray.png',
        'Medio Microfiber Cleaning Kit' => 'Medio Microfiber Cleaning Kit.png',
        'Medio Premium Hardcase' => 'Medio Premium Hardcase.png',
        'Medio Progressive Daily Comfort' => 'Medio Progressive Daily Comfort.png',
        'Medio Reader Clip On +2.00' => 'Medio Reader Clip On +2.00.png',
        'Medio Reader Slim +1.00' => 'Medio Reader Slim +1.00.png',
        'Medio Soft Cat Eye Rose' => 'Medio Soft Cat Eye Rose.png',
        'Medio UV400 Wayfarer' => 'Medio UV400 Wayfarer.png',
        'Medio WorkFlex Rectangle' => 'Medio WorkFlex Rectangle.png',
        'Oakley Holbrook Polarized' => 'Oakley Holbrook Polarized.png',
        'Oakley OX8046 Crosslink Zero' => 'Oakley OX8046 Crosslink Zero.png',
        'Paket Pemeriksaan Mata Lengkap' => 'Paket Pemeriksaan Mata Lengkap.png',
        'Ray-Ban RB3025 Aviator Classic' => 'Ray-Ban RB3025 Aviator Classic.png',
        'Ray-Ban RB5154 Clubmaster Classic' => 'Ray-Ban RB5154 Clubmaster Classic.png',
    ];

    public function run(): void
    {
        $sourceDir = public_path('images/foto_produk');
        $targetDir = storage_path('app/public/products/foto_produk');

        File::ensureDirectoryExists($targetDir);

        foreach ($this->photos as $productName => $fileName) {
            $product = Product::query()->where('name', $productName)->first();
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $fileName;

            if (! $product || ! File::exists($sourcePath)) {
                continue;
            }

            $relativePath = 'products/foto_produk/' . $product->slug . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
            $targetPath = storage_path('app/public/' . $relativePath);

            File::copy($sourcePath, $targetPath);

            $oldProductImages = $product->productImages()->pluck('image_path')->filter()->values()->all();
            $oldProductArrayImages = is_array($product->images) ? $product->images : [];
            $oldSvgPaths = collect([...$oldProductImages, ...$oldProductArrayImages, $product->og_image])
                ->filter(fn (?string $path): bool => is_string($path) && str_ends_with(strtolower($path), '.svg'))
                ->unique()
                ->values();

            $product->forceFill([
                'images' => [$relativePath],
                'og_image' => $relativePath,
            ])->save();

            $product->productImages()->delete();

            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $relativePath,
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
                'is_active' => true,
            ]);

            foreach ($oldSvgPaths as $oldSvgPath) {
                $absoluteSvgPath = storage_path('app/public/' . $oldSvgPath);

                if (File::exists($absoluteSvgPath)) {
                    File::delete($absoluteSvgPath);
                }
            }
        }
    }
}
