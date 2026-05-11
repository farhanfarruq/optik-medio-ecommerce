<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LevelMemberResource\Pages\CreateLevelMember;
use App\Filament\Resources\LevelMemberResource\Pages\EditLevelMember;
use App\Filament\Resources\LevelMemberResource\Pages\ListLevelMembers;
use App\Models\LevelMember;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LevelMemberResource extends Resource
{
    protected static ?string $model = LevelMember::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static string | \UnitEnum | null $navigationGroup = 'Pelanggan';
    protected static ?string $navigationLabel = 'Level Member';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Tier Membership')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\TextInput::make('min_points')->numeric()->required()->default(0),
                    Forms\Components\TextInput::make('discount_percentage')->numeric()->required()->default(0),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('min_points')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('discount_percentage')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
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
            'index' => ListLevelMembers::route('/'),
            'create' => CreateLevelMember::route('/create'),
            'edit' => EditLevelMember::route('/{record}/edit'),
        ];
    }
}
