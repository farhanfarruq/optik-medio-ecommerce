<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingRateResource\Pages\CreateShippingRate;
use App\Filament\Resources\ShippingRateResource\Pages\EditShippingRate;
use App\Filament\Resources\ShippingRateResource\Pages\ListShippingRates;
use App\Models\ShippingRate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static string | \UnitEnum | null $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Tarif Ongkir';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Tarif Ongkir Internal')
                ->schema([
                    Forms\Components\Select::make('expedition_id')->relationship('expedition', 'name')->required()->searchable()->preload(),
                    Forms\Components\TextInput::make('service_name')->required(),
                    Forms\Components\TextInput::make('service_code')->required(),
                    Forms\Components\TextInput::make('province')->required(),
                    Forms\Components\TextInput::make('province_id'),
                    Forms\Components\TextInput::make('city')->required(),
                    Forms\Components\TextInput::make('city_id'),
                    Forms\Components\TextInput::make('district')->required(),
                    Forms\Components\TextInput::make('district_id'),
                    Forms\Components\TextInput::make('postal_code'),
                    Forms\Components\TextInput::make('price')->numeric()->required(),
                    Forms\Components\TextInput::make('etd')->label('Estimasi'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('expedition.name')->label('Ekspedisi')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('service_name')->searchable(),
                Tables\Columns\TextColumn::make('district')->searchable(),
                Tables\Columns\TextColumn::make('city')->searchable(),
                Tables\Columns\TextColumn::make('province')->searchable(),
                Tables\Columns\TextColumn::make('price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('etd')->label('Estimasi')->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('expedition_id')->relationship('expedition', 'name')->label('Ekspedisi'),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingRates::route('/'),
            'create' => CreateShippingRate::route('/create'),
            'edit' => EditShippingRate::route('/{record}/edit'),
        ];
    }
}
