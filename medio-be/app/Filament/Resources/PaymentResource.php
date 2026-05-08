<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Konfirmasi Bayar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Placeholder::make('transaction_id')
                    ->label('Transaction ID')
                    ->content(fn (?Payment $record) => $record?->transaction_id ?? '-'),
                Forms\Components\TextInput::make('payment_method')
                    ->disabled(),
                Forms\Components\TextInput::make('gross_amount')
                    ->label('Amount')
                    ->numeric()
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'refund' => 'Refund',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('paid_at'),
                
                Section::make('Detail Konfirmasi Transfer')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_proof')
                            ->label('Bukti Pembayaran (Transfer Manual)')
                            ->content(function (?Payment $record) {
                                $url = $record?->order?->payment_proof_image 
                                    ? asset('storage/' . $record->order->payment_proof_image) 
                                    : null;
                                
                                if ($url) {
                                    return new \Illuminate\Support\HtmlString("
                                        <div style='background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 12px; padding: 16px; text-align: center;'>
                                            <a href='{$url}' target='_blank' style='display: inline-block;'>
                                                <img src='{$url}' alt='Bukti Transfer' style='max-height: 400px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);' />
                                            </a>
                                            <p style='color: #4b5563; font-size: 0.875rem; margin-top: 12px; font-weight: 600;'>👆 Klik gambar untuk memperbesar</p>
                                        </div>
                                    ");
                                }
                                return new \Illuminate\Support\HtmlString("
                                    <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; color: #dc2626; font-weight: bold; text-align: center;'>
                                        Belum ada bukti transfer yang diunggah.
                                    </div>
                                ");
                            }),
                        
                        Forms\Components\Placeholder::make('order_items')
                            ->label('Produk yang Dibeli')
                            ->content(function (?Payment $record) {
                                if (!$record?->order) return '-';
                                $itemsHtml = '<ul class="list-disc ml-5 space-y-1">';
                                foreach ($record->order->items as $item) {
                                    $itemsHtml .= "<li><b>{$item->quantity}x</b> {$item->product_name} - Rp " . number_format($item->price, 0, ',', '.') . "</li>";
                                }
                                $itemsHtml .= '</ul>';
                                return new \Illuminate\Support\HtmlString($itemsHtml);
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('order.user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success'   => 'success',
                        'pending'   => 'warning',
                        'failed'    => 'danger',
                        'expired'   => 'gray',
                        'cancelled' => 'gray',
                        'refund'    => 'info',
                        default     => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'success'   => 'heroicon-o-check-circle',
                        'pending'   => 'heroicon-o-clock',
                        'failed'    => 'heroicon-o-x-circle',
                        'expired'   => 'heroicon-o-exclamation-circle',
                        default     => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum dibayar'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => '⏳ Pending',
                        'success'   => '✅ Sukses',
                        'failed'    => '❌ Gagal',
                        'expired'   => '⏰ Expired',
                        'cancelled' => '🚫 Dibatalkan',
                        'refund'    => '↩️ Refund',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('unpaid_orders')
                    ->label('Menunggu Pembayaran')
                    ->query(fn ($query) => $query->where('status', 'pending')),
                Tables\Filters\Filter::make('today')
                    ->label('Bayar Hari Ini')
                    ->query(fn ($query) => $query->whereDate('paid_at', today())),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
