<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductExpansionPhotoSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDir = public_path('images/foto_produk');
        $targetDir = storage_path('app/public/products/foto_produk');

        File::ensureDirectoryExists($targetDir);

        foreach ($this->catalog() as $item) {
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $item['photo_file'];
            if (! File::exists($sourcePath)) {
                continue;
            }

            $product = Product::query()->where('slug', $item['slug'])->first();
            if (! $product) {
                continue;
            }

            $relativePath = 'products/foto_produk/' . $item['slug'] . '.' . pathinfo($item['photo_file'], PATHINFO_EXTENSION);
            $targetPath = storage_path('app/public/' . $relativePath);

            File::copy($sourcePath, $targetPath);

            $oldPlaceholderPaths = collect([
                ...(is_array($product->images) ? $product->images : []),
                $product->og_image,
                ...$product->productImages()->pluck('image_path')->all(),
            ])->filter(
                fn (?string $path): bool => is_string($path) && str_contains($path, 'products/seed-expansion/')
            )->unique();

            $product->forceFill([
                'images' => [$relativePath],
                'og_image' => $relativePath,
            ])->save();

            $product->productImages()->delete();

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $relativePath,
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
                'is_active' => true,
            ]);

            foreach ($oldPlaceholderPaths as $oldPath) {
                $absolute = storage_path('app/public/' . $oldPath);
                if (File::exists($absolute)) {
                    File::delete($absolute);
                }
            }
        }
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
}
