<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceClaimResource\Pages;
use App\Models\ServiceClaim;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ServiceClaimResource extends Resource
{
    protected static ?string $model = ServiceClaim::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Klaim Garansi';
    protected static ?int $navigationSort = 8;

    public static function getNavigationBadge(): ?string
    {
        $count = ServiceClaim::query()->whereIn('status', ['submitted', 'reviewing'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Grid::make(2)->schema([
                \Filament\Schemas\Components\Section::make('Detail Klaim User')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('claim_number')
                            ->label('Nomor Klaim')
                            ->content(fn (?ServiceClaim $record) => $record?->claim_number ?? '-'),
                        Forms\Components\Placeholder::make('user.name')
                            ->label('Pelanggan')
                            ->content(fn (?ServiceClaim $record) => $record?->user?->name ?? '-'),
                        Forms\Components\Placeholder::make('warranty.warranty_number')
                            ->label('Nomor Garansi')
                            ->content(fn (?ServiceClaim $record) => $record?->warranty?->warranty_number ?? 'Tanpa garansi'),
                        Forms\Components\Placeholder::make('warranty.product_name')
                            ->label('Produk')
                            ->content(fn (?ServiceClaim $record) => $record?->warranty?->product_name ?? '-'),
                        Forms\Components\Placeholder::make('claim_type_label')
                            ->label('Tipe Klaim')
                            ->content(fn (?ServiceClaim $record) => $record?->claim_type_label ?? '-'),
                        Forms\Components\Placeholder::make('is_covered_by_warranty')
                            ->label('Ditanggung Garansi')
                            ->content(fn (?ServiceClaim $record) => $record?->is_covered_by_warranty ? 'Ya' : 'Tidak'),
                        Forms\Components\Placeholder::make('description')
                            ->label('Deskripsi User')
                            ->content(fn (?ServiceClaim $record) => $record?->description ?? '-')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('images')
                            ->label('Bukti Upload')
                            ->content(fn (?ServiceClaim $record) => self::renderImageLinks($record))
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Keputusan Admin')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(self::statusOptions())
                            ->required()
                            ->label('Status'),
                        Forms\Components\TextInput::make('service_cost')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->label('Biaya Servis'),
                        Forms\Components\Toggle::make('is_covered_by_warranty')
                            ->label('Ditanggung Garansi'),
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Diselesaikan Pada'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan / Respons Admin')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('claim_number')
                    ->label('No. Klaim')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warranty.product_name')
                    ->label('Produk')
                    ->placeholder('Tanpa garansi')
                    ->limit(28)
                    ->searchable(),
                Tables\Columns\TextColumn::make('claim_type_label')
                    ->label('Tipe')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'submitted'   => 'warning',
                        'reviewing'   => 'info',
                        'approved'    => 'success',
                        'in_progress' => 'info',
                        'completed'   => 'success',
                        'rejected'    => 'danger',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_covered_by_warranty')
                    ->label('Covered')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusOptions()),
                Tables\Filters\TernaryFilter::make('is_covered_by_warranty')
                    ->label('Ditanggung Garansi'),
            ])
            ->actions([
                Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (ServiceClaim $record): bool => $record->status === 'submitted')
                    ->action(fn (ServiceClaim $record): bool => $record->update(['status' => 'reviewing'])),
                Actions\Action::make('approve')
                    ->label('ACC')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ServiceClaim $record): bool => in_array($record->status, ['submitted', 'reviewing'], true))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin')
                            ->rows(3),
                    ])
                    ->action(function (ServiceClaim $record, array $data): void {
                        $record->update([
                            'status'      => 'approved',
                            'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                        ]);
                        self::syncWarrantyStatus($record);
                    }),
                Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ServiceClaim $record): bool => in_array($record->status, ['submitted', 'reviewing'], true))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ServiceClaim $record, array $data): void {
                        $record->update([
                            'status'      => 'rejected',
                            'admin_notes' => $data['admin_notes'],
                            'resolved_at' => now(),
                        ]);
                        self::syncWarrantyStatus($record);
                    }),
                Actions\Action::make('complete')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ServiceClaim $record): bool => in_array($record->status, ['approved', 'in_progress'], true))
                    ->requiresConfirmation()
                    ->action(function (ServiceClaim $record): void {
                        $record->update([
                            'status'      => 'completed',
                            'resolved_at' => now(),
                        ]);
                        self::syncWarrantyStatus($record);
                    }),
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceClaims::route('/'),
            'edit'  => Pages\EditServiceClaim::route('/{record}/edit'),
        ];
    }

    private static function statusOptions(): array
    {
        return [
            'submitted'   => 'Diajukan',
            'reviewing'   => 'Direview',
            'approved'    => 'Disetujui',
            'in_progress' => 'Diproses',
            'completed'   => 'Selesai',
            'rejected'    => 'Ditolak',
        ];
    }

    private static function renderImageLinks(?ServiceClaim $record): HtmlString
    {
        $images = $record?->images ?? [];

        if ($images === []) {
            return new HtmlString('-');
        }

        $links = collect($images)
            ->map(function (string $path, int $index): string {
                $url = asset('storage/' . ltrim($path, '/'));
                $label = 'Bukti ' . ($index + 1);

                return "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"color: #c19a51; font-weight: 700; margin-right: 12px;\">{$label}</a>";
            })
            ->implode('');

        return new HtmlString($links);
    }

    public static function syncWarrantyStatus(ServiceClaim $claim): void
    {
        $warranty = $claim->warranty;

        if (! $warranty) {
            return;
        }

        if ($claim->status !== 'rejected') {
            $warranty->update(['status' => 'claimed']);
            return;
        }

        $hasOtherClaim = ServiceClaim::query()
            ->where('warranty_id', $warranty->id)
            ->where('id', '!=', $claim->id)
            ->whereIn('status', ['submitted', 'reviewing', 'approved', 'in_progress', 'completed'])
            ->exists();

        if (! $hasOtherClaim) {
            $warranty->update([
                'status' => $warranty->warranty_expires_at->copy()->endOfDay()->isPast() ? 'expired' : 'active',
            ]);
        }
    }
}
