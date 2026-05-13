<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    /**
     * Export semua produk aktif ke CSV.
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $products = Product::with('category')
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

            // Header row
            fputcsv($handle, [
                'id', 'name', 'sku', 'brand', 'category',
                'price', 'stock', 'weight',
                'gender', 'frame_shape', 'frame_material', 'frame_color',
                'is_active', 'is_best_seller', 'description',
            ]);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->sku ?? '',
                    $p->brand ?? '',
                    $p->category?->name ?? '',
                    $p->price,
                    $p->stock,
                    $p->weight,
                    $p->gender ?? '',
                    $p->frame_shape ?? '',
                    $p->frame_material ?? '',
                    $p->frame_color ?? '',
                    $p->is_active ? '1' : '0',
                    $p->is_best_seller ? '1' : '0',
                    $p->description ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import produk dari CSV yang di-upload.
     * Format: name, sku, brand, category_name, price, stock, weight, gender, frame_shape, frame_material, frame_color
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
        $headers = fgetcsv($handle); // skip header row
        $created = 0;
        $updated = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) {
                continue;
            }

            [$name, $sku, $brand, $categoryName, $price, $stock, $weight] = array_pad($row, 7, '');

            if (empty($name) || empty($price)) {
                $errors[] = "Baris dilewati: nama atau harga kosong.";
                continue;
            }

            // Cari atau buat kategori
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName ?: 'umum')],
                ['name' => $categoryName ?: 'Umum', 'is_active' => true]
            );

            $productData = [
                'category_id'  => $category->id,
                'name'         => trim($name),
                'brand'        => trim($brand) ?: null,
                'price'        => (float) str_replace([',', '.'], ['', '.'], $price),
                'stock'        => (int) $stock,
                'weight'       => (int) ($weight ?: 300),
                'is_active'    => true,
                'description'  => '',
            ];

            if (! empty($sku)) {
                $existing = Product::where('sku', trim($sku))->first();
                if ($existing) {
                    $existing->update($productData);
                    $updated++;
                    continue;
                }
                $productData['sku'] = trim($sku);
            }

            $productData['slug'] = Str::slug($name) . '-' . Str::random(5);
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
                        ->helperText('Format: name, sku, brand, category, price, stock, weight'),
                ])
                ->action(fn (array $data) => $this->import($data)),
        ];
    }
}
