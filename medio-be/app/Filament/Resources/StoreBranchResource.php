<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreBranchResource\Pages;
use App\Models\StoreBranch;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StoreBranchResource extends Resource
{
    protected static ?string $model = StoreBranch::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null   $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Cabang Toko';
    protected static ?int    $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(100),
            Forms\Components\TextInput::make('code')->required()->maxLength(20)->unique(StoreBranch::class, 'code', ignoreRecord: true),
            Forms\Components\Textarea::make('address')->required()->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make('city')->required(),
            Forms\Components\TextInput::make('province')->required(),
            Forms\Components\TextInput::make('phone')->tel(),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('maps_url')->url()->label('Google Maps URL')->columnSpanFull(),
            Forms\Components\TextInput::make('latitude')->numeric(),
            Forms\Components\TextInput::make('longitude')->numeric(),
            Forms\Components\TextInput::make('appointment_capacity')->numeric()->default(10)->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\KeyValue::make('operating_hours')
                ->label('Jam Operasional')
                ->keyLabel('Hari (mon, tue, ...)')
                ->valueLabel('Jam (09:00-18:00)')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('city')->searchable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('-'),
                Tables\Columns\TextColumn::make('appointment_capacity')->label('Kapasitas/Hari')->numeric(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStoreBranches::route('/'),
            'create' => Pages\CreateStoreBranch::route('/create'),
            'edit'   => Pages\EditStoreBranch::route('/{record}/edit'),
        ];
    }
}
