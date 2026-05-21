<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountResource\Pages;
use App\Models\Discount;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | \UnitEnum | null $navigationGroup = 'Promo & Diskon';
    protected static ?string $navigationLabel = 'Kode Diskon';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'percentage' => 'Persentase (%)',
                        'fixed' => 'Nominal Tetap (Rp)',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('value')
                    ->required()
                    ->numeric()
                    ->helperText('Jika persentase, isi 1-100. Jika nominal, isi jumlah Rupiah (misal: 50000)'),
                Forms\Components\TextInput::make('min_order_amount')
                    ->label('Minimal Belanja')
                    ->numeric()
                    ->helperText('Kosongkan jika tidak ada minimal belanja'),
                Forms\Components\DateTimePicker::make('start_date')
                    ->label('Tanggal Mulai Berlaku'),
                Forms\Components\DateTimePicker::make('end_date')
                    ->label('Tanggal Kedaluwarsa'),
                Forms\Components\TextInput::make('quota')
                    ->numeric()
                    ->label('Maksimal Penggunaan (Kosongi jika unlimited)'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Diskon')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'info',
                        'fixed'      => 'success',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(fn ($record) => $record->type === 'percentage' ? $record->value . '%' : 'Rp ' . number_format($record->value, 0, ',', '.')),
                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min. Belanja')
                    ->money('IDR')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Dipakai')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kedaluwarsa Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
                Tables\Filters\Filter::make('valid')
                    ->label('Masih Berlaku (Belum Expired)')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>', now());
                    })),
                Tables\Filters\Filter::make('expired')
                    ->label('Sudah Expired')
                    ->query(fn ($query) => $query->whereNotNull('end_date')->where('end_date', '<', now())),
                Tables\Filters\TrashedFilter::make()
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
        ];
    }
}
