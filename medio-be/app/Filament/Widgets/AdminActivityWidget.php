<?php

namespace App\Filament\Widgets;

use App\Models\OrderLog;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AdminActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = '🔍 Aktivitas Admin Terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderLog::with(['order', 'actedBy'])
                    ->whereHas('actedBy', fn ($q) => $q->whereIn('role', User::STAFF_ROLES))
                    ->latest()
                    ->limit(15)
            )
            ->columns([
                Tables\Columns\TextColumn::make('actedBy.name')
                    ->label('Admin')
                    ->weight('bold')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('actedBy.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'primary',
                        default => 'gray',
                    })
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'payment_verified'  => 'success',
                        'status_changed'    => 'info',
                        'order_shipped'     => 'primary',
                        'order_cancelled'   => 'danger',
                        'payment_proof_uploaded' => 'warning',
                        default             => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Keterangan')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
