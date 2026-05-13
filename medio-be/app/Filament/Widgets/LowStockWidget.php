<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = '⚠️ Produk Stok Menipis';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->orderBy('stock', 'asc')
                    ->limit(15)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->weight('bold')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('-')
                    ->copyable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->color(fn ($state): string => $state <= 0 ? 'danger' : 'warning')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Batas Min.')
                    ->numeric(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR'),
            ])
            ->actions([
                \Filament\Actions\Action::make('manage')
                    ->label('Kelola Stok')
                    ->url(fn (Product $record): string =>
                        route('filament.admin.resources.inventories.edit', $record)
                    )
                    ->icon('heroicon-o-arrow-right')
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
