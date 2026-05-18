<?php

namespace App\Filament\Resources;

use App\Enums\CommissionStatus;
use App\Filament\Resources\CommissionResource\Pages\EditCommission;
use App\Filament\Resources\CommissionResource\Pages\ListCommissions;
use App\Models\Commission;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'Afiliasi';
    protected static ?string $navigationLabel = 'Komisi';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Pencairan Komisi')
                ->schema([
                    Forms\Components\Placeholder::make('affiliator_name')
                        ->label('Afiliator')
                        ->content(fn (?Commission $record) => $record?->affiliator?->user?->name ?? '-'),
                    Forms\Components\TextInput::make('request_no')->disabled(),
                    Forms\Components\TextInput::make('requested_amount')->numeric()->disabled(),
                    Forms\Components\TextInput::make('approved_amount')->numeric(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Menunggu',
                            'processing' => 'Diproses',
                            'success' => 'Berhasil',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('requested_at')->disabled(),
                    Forms\Components\DateTimePicker::make('processed_at'),
                    Forms\Components\Select::make('processed_by')->relationship('processedBy', 'name')->searchable()->preload(),
                    Forms\Components\Textarea::make('admin_notes')->columnSpanFull(),
                ])
                ->columns(2),
            \Filament\Schemas\Components\Section::make('Tujuan Pencairan')
                ->schema([
                    Forms\Components\TextInput::make('payout_method')
                        ->label('Metode')
                        ->disabled(),
                    Forms\Components\TextInput::make('payout_bank_name')
                        ->label('Bank / E-Wallet')
                        ->disabled(),
                    Forms\Components\TextInput::make('payout_account_number')
                        ->label('Nomor Rekening / Akun')
                        ->disabled(),
                    Forms\Components\TextInput::make('payout_account_name')
                        ->label('Nama Pemilik Rekening')
                        ->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('request_no')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('affiliator.user.name')->label('Afiliator')->searchable(),
                Tables\Columns\TextColumn::make('payout_bank_name')->label('Bank')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('payout_account_number')->label('No. Rekening')->placeholder('-')->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('payout_account_name')->label('Nama Rekening')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('requested_amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('approved_amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('details_total')
                    ->label('Rincian Order')
                    ->getStateUsing(fn (Commission $record): string => $record->details()->count() . ' order / Rp ' . number_format((float) $record->details()->sum('commission_amount'), 0, ',', '.')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CommissionStatus | string | null $state): string => match (self::resolveStatusValue($state)) {
                        CommissionStatus::Pending->value => 'Menunggu',
                        CommissionStatus::Processing->value => 'Diproses',
                        CommissionStatus::Success->value => 'Berhasil',
                        CommissionStatus::Cancelled->value => 'Dibatalkan',
                        default => '-',
                    })
                    ->color(fn (CommissionStatus | string | null $state) => match (self::resolveStatusValue($state)) {
                        CommissionStatus::Pending->value => 'warning',
                        CommissionStatus::Processing->value => 'info',
                        CommissionStatus::Success->value => 'success',
                        CommissionStatus::Cancelled->value => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('requested_at')->dateTime('d M Y, H:i'),
                Tables\Columns\TextColumn::make('processed_at')->dateTime('d M Y, H:i')->placeholder('-'),
            ])
            ->actions([
                \Filament\Actions\Action::make('process')
                    ->label('Proses')
                    ->color('info')
                    ->visible(fn (Commission $record) => $record->status === CommissionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (Commission $record) => $record->update([
                        'status' => CommissionStatus::Processing,
                        'processed_by' => auth()->id(),
                        'processed_at' => now(),
                    ])),
                \Filament\Actions\Action::make('approve')
                    ->label('Tandai Berhasil')
                    ->color('success')
                    ->visible(fn (Commission $record) => in_array($record->status, [CommissionStatus::Pending, CommissionStatus::Processing], true))
                    ->requiresConfirmation()
                    ->action(fn (Commission $record) => $record->update([
                        'status' => CommissionStatus::Success,
                        'approved_amount' => $record->approved_amount ?: $record->requested_amount,
                        'processed_by' => auth()->id(),
                        'processed_at' => now(),
                    ])),
                \Filament\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->color('danger')
                    ->visible(fn (Commission $record) => $record->status !== CommissionStatus::Cancelled)
                    ->requiresConfirmation()
                    ->action(fn (Commission $record) => $record->update([
                        'status' => CommissionStatus::Cancelled,
                        'processed_by' => auth()->id(),
                        'processed_at' => now(),
                    ])),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
            'edit' => EditCommission::route('/{record}/edit'),
        ];
    }

    private static function resolveStatusValue(CommissionStatus | string | null $status): ?string
    {
        if ($status instanceof CommissionStatus) {
            return $status->value;
        }

        return $status;
    }
}
