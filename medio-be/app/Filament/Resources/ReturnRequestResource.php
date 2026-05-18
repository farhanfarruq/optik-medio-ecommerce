<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReturnRequestResource\Pages;
use App\Models\ReturnRequest;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Return';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Grid::make(2)->schema([

                \Filament\Schemas\Components\Section::make('Detail Pengajuan Return')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('order.order_number')
                            ->label('Nomor Order')
                            ->content(fn (?ReturnRequest $record) => $record?->order?->order_number ?? '-'),
                        Forms\Components\Placeholder::make('user.name')
                            ->label('Pelanggan')
                            ->content(fn (?ReturnRequest $record) => $record?->user?->name ?? '-'),
                        Forms\Components\Placeholder::make('reason')
                            ->label('Alasan Return')
                            ->content(fn (?ReturnRequest $record) => $record?->reason ?? '-')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('description')
                            ->label('Deskripsi')
                            ->content(fn (?ReturnRequest $record) => $record?->description ?? '-')
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Keputusan Admin')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending'  => 'Pending',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                            ])
                            ->required()->label('Status'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin')
                            ->rows(8),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pengajuan Return')
                    ->modalDescription('Setujui pengajuan pengembalian ini? Pelanggan akan mendapat notifikasi.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin (opsional)')
                            ->rows(3),
                    ])
                    ->action(function (ReturnRequest $record, array $data): void {
                        $record->update([
                            'status'      => 'approved',
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ReturnRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Pengajuan Return')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ReturnRequest $record, array $data): void {
                        $record->update([
                            'status'      => 'rejected',
                            'admin_notes' => $data['admin_notes'],
                        ]);
                    }),
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReturnRequests::route('/'),
            'edit' => Pages\EditReturnRequest::route('/{record}/edit'),
        ];
    }
}
