<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreCloseResource\Pages\CreateStoreClose;
use App\Filament\Resources\StoreCloseResource\Pages\EditStoreClose;
use App\Filament\Resources\StoreCloseResource\Pages\ListStoreCloses;
use App\Models\StoreClose;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StoreCloseResource extends Resource
{
    protected static ?string $model = StoreClose::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-no-symbol';
    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Mode Libur / Toko Tutup')
                ->schema([
                    Forms\Components\DateTimePicker::make('start_at')->required(),
                    Forms\Components\DateTimePicker::make('end_at')->required(),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('reason')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('start_at')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('end_at')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('reason')->limit(60)->wrap(),
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
            'index' => ListStoreCloses::route('/'),
            'create' => CreateStoreClose::route('/create'),
            'edit' => EditStoreClose::route('/{record}/edit'),
        ];
    }
}
