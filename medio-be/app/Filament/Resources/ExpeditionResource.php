<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpeditionResource\Pages\CreateExpedition;
use App\Filament\Resources\ExpeditionResource\Pages\EditExpedition;
use App\Filament\Resources\ExpeditionResource\Pages\ListExpeditions;
use App\Models\Expedition;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExpeditionResource extends Resource
{
    protected static ?string $model = Expedition::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Ekspedisi')
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                    
                    \Filament\Forms\Components\Repeater::make('services')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('service_code')
                                ->label('Kode Layanan (Misal: REG, JTR)')
                                ->required()
                                ->readOnly(),
                            Forms\Components\TextInput::make('service_name')
                                ->label('Nama Layanan (Opsional)'),
                            Forms\Components\Toggle::make('is_active')
                                ->label('Aktifkan')
                                ->default(true),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->disableItemCreation() // Disallow creating manually, system will auto-populate
                        ->disableItemDeletion() // Disallow deleting manually
                        ->label('Layanan Ekspedisi (Otomatis ditambahkan saat kustomer cek ongkir)'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('shipping_rates_count')->counts('shippingRates')->label('Tarif'),
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
            'index' => ListExpeditions::route('/'),
            'create' => CreateExpedition::route('/create'),
            'edit' => EditExpedition::route('/{record}/edit'),
        ];
    }
}
