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
    protected static ?string $navigationLabel = 'Return Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Placeholder::make('order.order_number')
                ->label('Order Number')
                ->content(fn (?ReturnRequest $record) => $record?->order?->order_number ?? '-'),
            Forms\Components\Placeholder::make('user.name')
                ->label('Customer')
                ->content(fn (?ReturnRequest $record) => $record?->user?->name ?? '-'),
            Forms\Components\Placeholder::make('reason')
                ->content(fn (?ReturnRequest $record) => $record?->reason ?? '-'),
            Forms\Components\Placeholder::make('description')
                ->content(fn (?ReturnRequest $record) => $record?->description ?? '-'),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),
            Forms\Components\Textarea::make('admin_notes')
                ->label('Admin Notes')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
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
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Return Request')
                    ->modalDescription('Setujui pengajuan pengembalian ini? Customer akan mendapat notifikasi.')
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
                    ->modalHeading('Tolak Return Request')
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
