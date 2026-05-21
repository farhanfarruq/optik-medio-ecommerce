<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Pages\ProductCsvImport;
use App\Filament\Pages\StockOpname;
use App\Filament\Resources\InventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventory extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('stock_opname')
                ->label('Stock Opname')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(StockOpname::getUrl()),
            Actions\Action::make('csv_import_export')
                ->label('Import / Export CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(ProductCsvImport::getUrl()),
        ];
    }
}
