<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductReviewResource\Pages;
use App\Models\ProductReview;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Ulasan Produk';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('is_approved')
                ->label('Status Moderasi')
                ->options([
                    1 => '✅ Approved — Tampilkan di website',
                    0 => '❌ Rejected — Sembunyikan',
                ])
                ->required(),
            Forms\Components\Placeholder::make('product.name')
                ->label('Produk')
                ->content(fn (?ProductReview $record) => $record?->product?->name ?? '-'),
            Forms\Components\Placeholder::make('user.name')
                ->label('Customer')
                ->content(fn (?ProductReview $record) => $record?->user?->name ?? '-'),
            Forms\Components\Placeholder::make('rating')
                ->label('Rating')
                ->content(fn (?ProductReview $record) => str_repeat('⭐', $record?->rating ?? 0)),
            Forms\Components\Placeholder::make('comment')
                ->label('Komentar')
                ->content(fn (?ProductReview $record) => $record?->comment ?? '(tidak ada komentar)')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Komentar')
                    ->limit(60)
                    ->placeholder('(tidak ada komentar)'),
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Disetujui?')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Status Moderasi')
                    ->trueLabel('Approved')
                    ->falseLabel('Pending / Ditolak')
                    ->placeholder('Semua'),
                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        5 => '⭐⭐⭐⭐⭐ (5)',
                        4 => '⭐⭐⭐⭐ (4)',
                        3 => '⭐⭐⭐ (3)',
                        2 => '⭐⭐ (2)',
                        1 => '⭐ (1)',
                    ])
                    ->label('Filter Rating'),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProductReview $record): bool => !$record->is_approved)
                    ->requiresConfirmation()
                    ->action(fn (ProductReview $record) => $record->update(['is_approved' => true])),
                \Filament\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ProductReview $record): bool => (bool) $record->is_approved)
                    ->requiresConfirmation()
                    ->action(fn (ProductReview $record) => $record->update(['is_approved' => false])),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('approve_all')
                        ->label('Approve Semua')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                    \Filament\Actions\BulkAction::make('reject_all')
                        ->label('Tolak Semua')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_approved' => false])),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReviews::route('/'),
            'edit'  => Pages\EditProductReview::route('/{record}/edit'),
        ];
    }
}
