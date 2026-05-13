<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Models\Cart;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = Cart::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Abandoned Cart';
    protected static ?int $navigationSort = 5;

    /**
     * Hanya tampilkan cart yang abandoned atau active dengan last_activity > 1 jam.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('user_id')
            ->where(function (Builder $q) {
                $q->where('status', 'abandoned')
                  ->orWhere(function (Builder $q2) {
                      $q2->where('status', 'active')
                         ->where('last_activity_at', '<', now()->subHour());
                  });
            })
            ->with(['user', 'items.product']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->getStateUsing(fn (Cart $record) => $record->items->sum('quantity'))
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('cart_value')
                    ->label('Estimasi Nilai')
                    ->getStateUsing(function (Cart $record) {
                        $total = $record->items->sum(
                            fn ($item) => ($item->product?->price ?? 0) * $item->quantity
                        );
                        return 'Rp ' . number_format($total, 0, ',', '.');
                    })
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'abandoned' => 'danger',
                        'active'    => 'warning',
                        'merged'    => 'info',
                        'converted' => 'success',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Aktivitas Terakhir')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->since(),
                Tables\Columns\IconColumn::make('reminder_sent')
                    ->label('Reminder Terkirim')
                    ->getStateUsing(fn (Cart $record) => $record->abandoned_reminder_sent_at !== null)
                    ->boolean(),
                Tables\Columns\TextColumn::make('abandoned_reminder_sent_at')
                    ->label('Reminder Dikirim')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum dikirim')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => '⏳ Masih Aktif (>1 jam)',
                        'abandoned' => '🛒 Abandoned',
                    ]),
                Tables\Filters\Filter::make('no_reminder')
                    ->label('Belum Dapat Reminder')
                    ->query(fn ($query) => $query->whereNull('abandoned_reminder_sent_at')),
                Tables\Filters\Filter::make('high_value')
                    ->label('Nilai Tinggi (estimasi)')
                    ->query(fn ($query) => $query->whereHas('items')),
            ])
            ->actions([
                \Filament\Actions\Action::make('view_items')
                    ->label('Lihat Item')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Cart $record) => 'Item Cart — ' . $record->user?->name)
                    ->modalContent(function (Cart $record) {
                        $items = $record->items->map(function ($item) {
                            $product = $item->product;
                            $price   = ($product?->price ?? 0) * $item->quantity;
                            return "• {$product?->name} (x{$item->quantity}) — Rp " . number_format($price, 0, ',', '.');
                        })->join("\n");

                        return view('filament.modals.text-content', ['content' => $items ?: 'Tidak ada item.']);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('mark_abandoned')
                        ->label('Tandai Abandoned')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => 'abandoned'])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}
