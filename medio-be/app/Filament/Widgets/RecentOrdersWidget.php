<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = '📦 Pesanan Terbaru (Perlu Tindakan)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::with(['user', 'payment'])
                    ->whereIn('status', ['unpaid', 'paid', 'processing'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid'     => 'warning',
                        'paid'       => 'info',
                        'processing' => 'primary',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\Action::make('lihat')
                    ->url(fn (Order $record): string => route('filament.admin.resources.orders.view', $record))
                    ->icon('heroicon-o-eye')
                    ->openUrlInNewTab(),
            ]);
    }
}
