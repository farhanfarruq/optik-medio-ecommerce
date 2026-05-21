<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductCsvImport extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected string $view = 'filament.pages.product-csv-import';
    protected static ?string $navigationLabel = 'Import/Export CSV';
    protected static string | \UnitEnum | null $navigationGroup  = 'Produk';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $title           = 'Import / Export Produk CSV';

    public ?string $csvContent = null;
    public string  $importResult = '';

    private const CSV_COLUMNS = [
        'id',
        'name',
        'slug',
        'sku',
        'brand',
        'category',
        'condition',
        'description',
        'image_paths',
        'image_urls',
        'price',
        'stock',
        'low_stock_threshold',
        'weight',
        'dimensions',
        'gender',
        'frame_shape',
        'frame_material',
        'frame_color',
        'face_size_fit',
        'lens_width',
        'bridge_width',
        'temple_length',
        'frame_width',
        'tags',
        'campaign_tags',
        'is_active',
        'is_not_for_sale',
        'is_best_seller',
        'is_featured',
        'is_new',
        'recommendation_priority',
        'is_prescription_required',
        'prescription_rules',
        'google_product_category',
        'gtin',
        'mpn',
        'meta_title',
        'meta_description',
        'canonical_slug',
        'og_image',
    ];

    /**
     * Export semua produk aktif ke CSV.
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $products = Product::with(['category', 'activeProductImages'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            // BOM untuk Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, self::CSV_COLUMNS);

            foreach ($products as $p) {
                $imagePaths = $this->imagePaths($p);

                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->slug,
                    $p->sku ?? '',
                    $p->brand ?? '',
                    $p->category?->name ?? '',
                    $p->condition ?? 'new',
                    $p->description ?? '',
                    $this->joinList($imagePaths),
                    $this->joinList(array_map(fn (string $path): string => $this->storageUrl($path), $imagePaths)),
                    $p->price,
                    $p->stock,
                    $p->low_stock_threshold,
                    $p->weight,
                    $this->encodeJson($p->dimensions),
                    $p->gender ?? '',
                    $p->frame_shape ?? '',
                    $p->frame_material ?? '',
                    $p->frame_color ?? '',
                    $p->face_size_fit ?? '',
                    $p->lens_width ?? '',
                    $p->bridge_width ?? '',
                    $p->temple_length ?? '',
                    $p->frame_width ?? '',
                    $this->joinList($p->tags ?? []),
                    $this->joinList($p->campaign_tags ?? []),
                    $p->is_active ? '1' : '0',
                    $p->is_not_for_sale ? '1' : '0',
                    $p->is_best_seller ? '1' : '0',
                    $p->is_featured ? '1' : '0',
                    $p->is_new ? '1' : '0',
                    $p->recommendation_priority ?? 0,
                    $p->is_prescription_required ? '1' : '0',
                    $this->encodeJson($p->prescription_rules),
                    $p->google_product_category ?? '',
                    $p->gtin ?? '',
                    $p->mpn ?? '',
                    $p->meta_title ?? '',
                    $p->meta_description ?? '',
                    $p->canonical_slug ?? '',
                    $p->og_image ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import produk dari CSV yang di-upload. Header dibaca berdasarkan nama kolom.
     */
    public function import(array $data): void
    {
        if (empty($data['csv_file'])) {
            Notification::make()->title('File CSV tidak ditemukan.')->danger()->send();
            return;
        }

        $filePath = storage_path('app/public/' . $data['csv_file']);
        if (! file_exists($filePath)) {
            Notification::make()->title('File tidak ditemukan di server.')->danger()->send();
            return;
        }

        $handle  = fopen($filePath, 'r');
        $headers = $this->normalizeHeaders(fgetcsv($handle) ?: []);
        $created = 0;
        $updated = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $record = $this->rowToRecord($headers, $row);
            $name = trim($record['name'] ?? '');
            $sku = trim($record['sku'] ?? '');
            $slug = trim($record['slug'] ?? '');
            $price = $record['price'] ?? '';

            if (empty($name) || empty($price)) {
                $errors[] = "Baris dilewati: nama atau harga kosong.";
                continue;
            }

            $categoryName = trim($record['category'] ?? 'Umum') ?: 'Umum';
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'is_active' => true]
            );

            $productData = [
                'category_id' => $category->id,
                'name' => $name,
                'brand' => $this->nullableString($record['brand'] ?? null),
                'condition' => $this->nullableString($record['condition'] ?? null) ?: 'new',
                'description' => $this->nullableString($record['description'] ?? null) ?: '',
                'price' => $this->moneyValue($price),
                'stock' => (int) ($record['stock'] ?? 0),
                'low_stock_threshold' => (int) ($record['low_stock_threshold'] ?? 3),
                'weight' => (int) (($record['weight'] ?? null) ?: 300),
                'dimensions' => $this->jsonValue($record['dimensions'] ?? null),
                'gender' => $this->nullableString($record['gender'] ?? null),
                'frame_shape' => $this->nullableString($record['frame_shape'] ?? null),
                'frame_material' => $this->nullableString($record['frame_material'] ?? null),
                'frame_color' => $this->nullableString($record['frame_color'] ?? null),
                'face_size_fit' => $this->nullableString($record['face_size_fit'] ?? null),
                'lens_width' => $this->nullableInt($record['lens_width'] ?? null),
                'bridge_width' => $this->nullableInt($record['bridge_width'] ?? null),
                'temple_length' => $this->nullableInt($record['temple_length'] ?? null),
                'frame_width' => $this->nullableInt($record['frame_width'] ?? null),
                'tags' => $this->splitList($record['tags'] ?? ''),
                'campaign_tags' => $this->splitList($record['campaign_tags'] ?? ''),
                'is_active' => $this->boolValue($record['is_active'] ?? '1'),
                'is_not_for_sale' => $this->boolValue($record['is_not_for_sale'] ?? '0'),
                'is_best_seller' => $this->boolValue($record['is_best_seller'] ?? '0'),
                'is_featured' => $this->boolValue($record['is_featured'] ?? '0'),
                'is_new' => $this->boolValue($record['is_new'] ?? '0'),
                'recommendation_priority' => (int) ($record['recommendation_priority'] ?? 0),
                'is_prescription_required' => $this->boolValue($record['is_prescription_required'] ?? '0'),
                'prescription_rules' => $this->jsonValue($record['prescription_rules'] ?? null),
                'google_product_category' => $this->nullableString($record['google_product_category'] ?? null),
                'gtin' => $this->nullableString($record['gtin'] ?? null),
                'mpn' => $this->nullableString($record['mpn'] ?? null),
                'meta_title' => $this->nullableString($record['meta_title'] ?? null),
                'meta_description' => $this->nullableString($record['meta_description'] ?? null),
                'canonical_slug' => $this->nullableString($record['canonical_slug'] ?? null),
                'og_image' => $this->nullableString($record['og_image'] ?? null),
            ];

            if (array_key_exists('image_paths', $record) || array_key_exists('image_urls', $record)) {
                $productData['images'] = $this->imagePathsFromRecord($record);
            }

            if (! empty($sku)) {
                $existing = Product::where('sku', $sku)->first();
                if ($existing) {
                    $existing->update($productData);
                    $updated++;
                    continue;
                }
                $productData['sku'] = $sku;
            }

            $productData['slug'] = $slug ?: Str::slug($name) . '-' . Str::random(5);
            Product::create($productData);
            $created++;
        }

        fclose($handle);
        unlink($filePath);

        $msg = "Import selesai: {$created} produk baru, {$updated} diperbarui.";
        if ($errors) {
            $msg .= ' ' . count($errors) . ' baris dilewati.';
        }

        Notification::make()->title($msg)->success()->send();
        $this->importResult = $msg;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('export'),

            Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('File CSV')
                        ->disk('public')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->required()
                        ->helperText('Gunakan header lengkap dari Export CSV. Kolom image_paths berisi path storage, dipisah dengan tanda ;'),
                ])
                ->action(fn (array $data) => $this->import($data)),
        ];
    }

    private function imagePaths(Product $product): array
    {
        if ($product->relationLoaded('activeProductImages') && $product->activeProductImages->isNotEmpty()) {
            return $product->activeProductImages
                ->pluck('image_path')
                ->filter()
                ->values()
                ->all();
        }

        return is_array($product->images) ? array_values(array_filter($product->images)) : [];
    }

    private function storageUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url(Storage::url($path));
    }

    private function joinList(?array $items): string
    {
        return implode('; ', array_values(array_filter($items ?? [], fn ($item) => filled($item))));
    }

    private function splitList(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(preg_split('/[;|]/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function encodeJson(mixed $value): string
    {
        if (! is_array($value) || $value === []) {
            return '';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function jsonValue(?string $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header): string => Str::of((string) $header)->replace("\xEF\xBB\xBF", '')->trim()->lower()->toString(), $headers);
    }

    private function rowToRecord(array $headers, array $row): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $record[$header] = $row[$index] ?? '';
        }

        return $record;
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => ! filled($value));
    }

    private function imagePathsFromRecord(array $record): array
    {
        $paths = $this->splitList($record['image_paths'] ?? '');

        if ($paths === []) {
            $paths = collect($this->splitList($record['image_urls'] ?? ''))
                ->map(function (string $url): ?string {
                    $path = parse_url($url, PHP_URL_PATH);

                    if (! $path || ! str_contains($path, '/storage/')) {
                        return null;
                    }

                    return Str::after($path, '/storage/');
                })
                ->filter()
                ->values()
                ->all();
        }

        return $paths;
    }

    private function moneyValue(string $value): float
    {
        $normalized = trim($value);

        if (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return (float) preg_replace('/[^0-9.]/', '', $normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function boolValue(mixed $value): bool
    {
        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'ya', 'aktif', 'active'], true);
    }
}
