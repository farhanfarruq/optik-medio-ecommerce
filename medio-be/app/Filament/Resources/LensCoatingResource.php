<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LensCoatingResource\Pages;
use App\Models\LensCoating;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LensCoatingResource extends Resource
{
    protected static ?string $model = LensCoating::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Coating Lensa';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('price')
                ->numeric()
                ->prefix('Rp')
                ->default(0)
                ->required(),
            Forms\Components\Textarea::make('description')
                ->maxLength(65535)
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
                Tables\Columns\TextColumn::make('price')->money('IDR')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLensCoatings::route('/'),
            'create' => Pages\CreateLensCoating::route('/create'),
            'edit' => Pages\EditLensCoating::route('/{record}/edit'),
        ];
    }
}
