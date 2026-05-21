<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Product;
use App\Models\StockAdjustment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null   $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Inventori';
    protected static ?int    $navigationSort  = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label('Nama Produk')
                ->disabled(),
            Forms\Components\TextInput::make('sku')
                ->label('SKU')
                ->disabled(),
            Forms\Components\TextInput::make('stock')
                ->label('Stok Saat Ini')
                ->numeric()
                ->disabled(),
            Forms\Components\TextInput::make('low_stock_threshold')
                ->label('Batas Stok Minimum (Alert)')
                ->numeric()
                ->minValue(0)
                ->required()
                ->helperText('Alert akan muncul jika stok ≤ nilai ini.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->where('is_active', true))
            ->defaultSort('stock', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->weight('bold')
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->sortable()
                    ->color(fn (Product $record): string =>
                        $record->stock <= 0
                            ? 'danger'
                            : ($record->isLowStock() ? 'warning' : 'success')
                    )
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Batas Min.')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_low_stock')
                    ->label('Low Stock')
                    ->getStateUsing(fn (Product $record) => $record->isLowStock())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stok Menipis')
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<=', 'low_stock_threshold')),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Habis')
                    ->query(fn (Builder $query) => $query->where('stock', '<=', 0)),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\Action::make('adjust_stock')
                    ->label('Sesuaikan Stok')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('quantity_change')
                            ->label('Perubahan Stok')
                            ->numeric()
                            ->required()
                            ->helperText('Gunakan angka positif untuk tambah, negatif untuk kurang. Contoh: +10 atau -5'),
                        Forms\Components\Select::make('reason')
                            ->label('Alasan')
                            ->options([
                                'manual_adjustment' => 'Penyesuaian Manual',
                                'import'            => 'Import Stok Baru',
                                'correction'        => 'Koreksi Stok',
                                'order_returned'    => 'Retur Pesanan',
                            ])
                            ->required()
                            ->default('manual_adjustment'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->placeholder('Opsional: alasan detail penyesuaian stok'),
                    ])
                    ->action(function (Product $record, array $data): void {
                        StockAdjustment::adjust(
                            product:        $record,
                            quantityChange: (int) $data['quantity_change'],
                            reason:         $data['reason'],
                            notes:          $data['notes'] ?? null,
                            adjustedBy:     auth()->id(),
                        );
                    })
                    ->successNotificationTitle('Stok berhasil disesuaikan.'),

                \Filament\Actions\Action::make('view_history')
                    ->label('Riwayat')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (Product $record) => 'Riwayat Stok — ' . $record->name)
                    ->modalContent(function (Product $record) {
                        $adjustments = StockAdjustment::where('product_id', $record->id)
                            ->with('adjustedBy')
                            ->latest()
                            ->limit(20)
                            ->get();

                        $lines = $adjustments->map(function ($adj) {
                            $sign   = $adj->quantity_change >= 0 ? '+' : '';
                            $by     = $adj->adjustedBy?->name ?? 'System';
                            $date   = $adj->created_at->format('d/m/Y H:i');
                            return "{$date} | {$sign}{$adj->quantity_change} ({$adj->quantity_before}→{$adj->quantity_after}) | {$adj->reason_label} | {$by}" .
                                   ($adj->notes ? " | {$adj->notes}" : '');
                        })->join("\n");

                        return view('filament.modals.text-content', [
                            'content' => $lines ?: 'Belum ada riwayat penyesuaian stok.',
                        ]);
                    }),

                Actions\EditAction::make()
                    ->label('Edit Threshold'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('set_threshold')
                        ->label('Set Batas Minimum')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->form([
                            Forms\Components\TextInput::make('low_stock_threshold')
                                ->label('Batas Stok Minimum')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->action(fn ($records, array $data) =>
                            $records->each->update(['low_stock_threshold' => $data['low_stock_threshold']])
                        )
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventory::route('/'),
            'edit'  => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
