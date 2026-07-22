<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    private const ROLE_OPTIONS = [
        'user' => '👤 Customer',
        'admin' => '🔧 Admin',
    ];

    protected static ?string $model = User::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Pelanggan';
    protected static ?string $navigationLabel = 'User';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options(self::ROLE_OPTIONS)
                    ->required(),
                Forms\Components\TextInput::make('loyalty_points')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ROLE_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'user'            => 'primary',
                        'admin'           => 'danger',
                        default           => 'gray',
                    }),
                Tables\Columns\TextColumn::make('loyalty_points')->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(self::ROLE_OPTIONS)
                    ->multiple(),
            ])
            ->actions([
                \Filament\Actions\Action::make('view_orders')
                    ->label('Pesanan')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->url(fn (User $record): string => route('filament.admin.resources.orders.index', ['tableFilters[user_id][value]' => $record->id]))
                    ->visible(fn (User $record): bool => $record->role === 'user'),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
            ])
            ->bulkActions([ \Filament\Actions\BulkActionGroup::make([ \Filament\Actions\DeleteBulkAction::make() ]) ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
