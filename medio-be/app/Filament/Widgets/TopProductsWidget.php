<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = '🏆 Produk Terlaris (Bulan Ini)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::withCount(['orderItems as total_sold' => function (Builder $query) {
                    $query->whereHas('order', fn ($q) => $q
                        ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                        ->whereMonth('created_at', now()->month)
                    );
                }])
                ->where('is_active', true)
                ->orderByDesc('total_sold')
                ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->label('')
                    ->disk('public')
                    ->height(40)
                    ->width(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Terjual Bulan Ini')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->color(fn ($state): string => $state < 5 ? 'danger' : ($state < 20 ? 'warning' : 'success')),
            ]);
    }
}
