<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCompatibilityResource\Pages;
use App\Models\ProductCompatibility;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductCompatibilityResource extends Resource
{
    protected static ?string $model = ProductCompatibility::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Kompatibilitas Produk';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('frame_product_id')
                ->relationship('frameProduct', 'name', fn ($query) => $query->where('is_active', true))
                ->label('Frame Product')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('lens_option_id')
                ->relationship('lensOption', 'name', fn ($query) => $query->where('is_active', true))
                ->label('Lens Option')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\KeyValue::make('compatibility_rule')
                ->label('Compatibility Rule')
                ->keyLabel('Rule')
                ->valueLabel('Value')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('frameProduct.name')->label('Frame')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('lensOption.name')->label('Lens Option')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('lensOption.type')->label('Lens Type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCompatibilities::route('/'),
            'create' => Pages\CreateProductCompatibility::route('/create'),
            'edit' => Pages\EditProductCompatibility::route('/{record}/edit'),
        ];
    }
}
