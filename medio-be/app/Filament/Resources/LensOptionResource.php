<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LensOptionResource\Pages;
use App\Models\LensOption;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LensOptionResource extends Resource
{
    protected static ?string $model = LensOption::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-eye';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Opsi Lensa';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Informasi Opsi')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Opsi')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->label('Jenis Opsi')
                        ->helperText('Pisahkan fungsi dasar lensa dari fitur/coating dan ketebalan lensa.')
                        ->options(self::typeOptions())
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('base_price')
                        ->label('Harga Tambahan')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->required()
                        ->default(true),
                ])
                ->columns(2),
            \Filament\Schemas\Components\Section::make('Aturan Resep')
                ->description('Opsional. Digunakan jika ada aturan kompatibilitas tambahan untuk resep tertentu.')
                ->schema([
                    Forms\Components\KeyValue::make('prescription_rules')
                        ->label('Aturan Resep')
                        ->keyLabel('Nama Aturan')
                        ->valueLabel('Nilai')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Opsi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::typeLabel($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Harga Tambahan')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(self::typeOptions()),
            ])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLensOptions::route('/'),
            'create' => Pages\CreateLensOption::route('/create'),
            'edit' => Pages\EditLensOption::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return [
            'single_vision' => 'Single Vision',
            'progressive' => 'Progresif / Bifokal',
            'reading' => 'Single Vision Baca',
            'blue_light' => 'Blue Light',
            'photochromic' => 'Photochromic',
            'high_index' => 'High Index',
            'anti_radiation' => 'Anti Radiasi',
        ];
    }

    private static function typeLabel(?string $state): string
    {
        return self::typeOptions()[$state ?? ''] ?? ($state ?: '-');
    }
}
