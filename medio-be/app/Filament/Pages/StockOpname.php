<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\StockAdjustment;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class StockOpname extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected string $view = 'filament.pages.stock-opname';
    protected static ?string $navigationLabel = 'Stock Opname';
    protected static string | \UnitEnum | null $navigationGroup  = 'Produk';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $title           = 'Stock Opname';

    /**
     * Data form: array of [product_id => actual_count]
     */
    public array $stockData = [];
    public string $notes    = '';

    public function mount(): void
    {
        // Load semua produk aktif
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock']);

        foreach ($products as $product) {
            $this->stockData[$product->id] = [
                'name'           => $product->name,
                'sku'            => $product->sku ?? '-',
                'current_stock'  => $product->stock,
                'actual_count'   => $product->stock, // default = stok saat ini
            ];
        }
    }

    public function save(): void
    {
        $adjustments = 0;

        DB::transaction(function () use (&$adjustments): void {
            foreach ($this->stockData as $productId => $data) {
                $actualCount   = (int) ($data['actual_count'] ?? 0);
                $currentStock  = (int) ($data['current_stock'] ?? 0);
                $quantityChange = $actualCount - $currentStock;

                if ($quantityChange === 0) {
                    continue;
                }

                $product = Product::find($productId);
                if (! $product) {
                    continue;
                }

                StockAdjustment::adjust(
                    product:        $product,
                    quantityChange: $quantityChange,
                    reason:         'correction',
                    notes:          $this->notes ?: 'Stock opname',
                    adjustedBy:     auth()->id(),
                );

                // Update current_stock di form data
                $this->stockData[$productId]['current_stock'] = $actualCount;
                $adjustments++;
            }
        });

        if ($adjustments > 0) {
            Notification::make()
                ->title("Stock opname selesai. {$adjustments} produk disesuaikan.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tidak ada perubahan stok.')
                ->info()
                ->send();
        }

        $this->notes = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Penyesuaian')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Stock Opname')
                ->modalDescription('Semua perbedaan stok akan dicatat sebagai penyesuaian. Lanjutkan?')
                ->action('save'),
        ];
    }
}
