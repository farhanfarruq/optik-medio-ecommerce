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
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('type')
                ->options([
                    'single_vision' => 'Single Vision',
                    'progressive' => 'Progressive',
                    'reading' => 'Reading',
                    'blue_light' => 'Blue Light',
                    'photochromic' => 'Photochromic',
                    'high_index' => 'High Index',
                    'anti_radiation' => 'Anti Radiation',
                ])
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('base_price')
                ->numeric()
                ->prefix('Rp')
                ->default(0)
                ->required(),
            Forms\Components\KeyValue::make('prescription_rules')
                ->label('Prescription Rules')
                ->keyLabel('Rule')
                ->valueLabel('Value')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('base_price')->money('IDR')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'single_vision' => 'Single Vision',
                        'progressive' => 'Progressive',
                        'reading' => 'Reading',
                        'blue_light' => 'Blue Light',
                        'photochromic' => 'Photochromic',
                        'high_index' => 'High Index',
                        'anti_radiation' => 'Anti Radiation',
                    ]),
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
}
