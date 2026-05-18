<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarrantyResource\Pages;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Warranty;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WarrantyResource extends Resource
{
    protected static ?string $model = Warranty::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-shield-check';
    protected static string | \UnitEnum | null   $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Garansi & Servis';
    protected static ?int    $navigationSort  = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('warranty_number')
                ->label('No. Garansi')
                ->default(fn () => Warranty::generateNumber())
                ->required()
                ->disabled()
                ->dehydrated()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('user_id')
                ->label('Pelanggan')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('order_id')
                ->label('Order')
                ->options(fn () => Order::query()->latest()->pluck('order_number', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('order_item_id')
                ->label('Item Order')
                ->options(fn () => OrderItem::query()->latest()->pluck('product_name', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('product_name')
                ->label('Produk')
                ->required(),
            Forms\Components\TextInput::make('product_sku')
                ->label('SKU'),
            Forms\Components\DatePicker::make('purchase_date')
                ->label('Tanggal Beli')
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('warranty_months')
                ->label('Durasi (Bulan)')
                ->numeric()
                ->default(12)
                ->required(),
            Forms\Components\DatePicker::make('warranty_expires_at')
                ->label('Garansi Berakhir')
                ->default(now()->addMonths(12))
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'active' => 'Aktif',
                    'expired' => 'Kadaluarsa',
                    'claimed' => 'Diklaim',
                    'void' => 'Void',
                ])
                ->default('active')
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('warranty_expires_at', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('warranty_number')
                    ->label('No. Garansi')
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produk')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Tgl Beli')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('warranty_expires_at')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->color(fn (Warranty $record) => $record->warranty_expires_at->isPast() ? 'danger' : ($record->daysRemaining() < 30 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'claimed' => 'warning',
                        'void' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'expired' => 'Kadaluarsa',
                        'claimed' => 'Diklaim',
                        'void' => 'Void',
                    ]),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Berakhir dalam 30 Hari')
                    ->query(fn ($query) => $query->where('warranty_expires_at', '<=', now()->addDays(30))->where('status', 'active')),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarranties::route('/'),
            'create' => Pages\CreateWarranty::route('/create'),
            'edit'  => Pages\EditWarranty::route('/{record}/edit'),
        ];
    }
}
