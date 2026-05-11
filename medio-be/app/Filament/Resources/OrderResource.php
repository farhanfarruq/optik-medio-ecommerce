<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Order';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->required(),
                Forms\Components\Select::make('bank_id')
                    ->label('Bank Tujuan')
                    ->relationship('bank', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Bank (Opsional)'),
                Forms\Components\TextInput::make('tracking_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('subtotal')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('shipping_cost')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('total_price')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('courier')
                    ->disabled(),
                Forms\Components\TextInput::make('courier_service')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('shipped_at'),
                Forms\Components\DateTimePicker::make('delivered_at'),
                
                \Filament\Schemas\Components\Section::make('Detail Konfirmasi Transfer & Pembelian')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_proof')
                            ->label('Bukti Pembayaran (Transfer Manual)')
                            ->content(function (?Order $record) {
                                $url = $record?->payment_proof_image 
                                    ? asset('storage/' . $record->payment_proof_image) 
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
                            ->content(function (?Order $record) {
                                if (!$record) return '-';
                                $itemsHtml = '<ul class="list-disc ml-5 space-y-1">';
                                foreach ($record->items as $item) {
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
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bank.name')
                    ->label('Bank Tujuan')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('items.product_name')
                    ->label('Produk')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid'     => 'warning',
                        'paid'       => 'info',
                        'processing' => 'primary',
                        'shipped'    => 'info',
                        'delivered'  => 'success',
                        'cancelled'  => 'danger',
                        'refunded'   => 'gray',
                        default      => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'unpaid'     => 'heroicon-o-clock',
                        'paid'       => 'heroicon-o-banknotes',
                        'processing' => 'heroicon-o-cog',
                        'shipped'    => 'heroicon-o-truck',
                        'delivered'  => 'heroicon-o-check-circle',
                        'cancelled'  => 'heroicon-o-x-circle',
                        'refunded'   => 'heroicon-o-arrow-uturn-left',
                        default      => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\IconColumn::make('is_payment_verified')
                    ->label('Bayar Terverifikasi')
                    ->boolean(),
                Tables\Columns\TextColumn::make('courier')
                    ->label('Kurir')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unpaid'     => '⏳ Belum Bayar',
                        'paid'       => '💰 Sudah Bayar',
                        'processing' => '🔄 Diproses',
                        'shipped'    => '🚚 Dikirim',
                        'delivered'  => '✅ Diterima',
                        'cancelled'  => '❌ Dibatalkan',
                        'refunded'   => '↩️ Refund',
                    ])
                    ->multiple()
                    ->label('Filter Status'),
                Tables\Filters\Filter::make('needs_action')
                    ->label('Perlu Tindakan (Paid → Processing)')
                    ->query(fn ($query) => $query->where('status', 'paid')),
                Tables\Filters\TernaryFilter::make('is_payment_verified')
                    ->label('Verifikasi Pembayaran'),
                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\Action::make('process')
                    ->label('Proses')
                    ->icon('heroicon-o-cog')
                    ->color('primary')
                    ->visible(fn (Order $record): bool => $record->status === 'paid' || ($record->status === 'unpaid' && $record->payment?->paymentMethod?->code === 'cod'))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Proses Pesanan')
                    ->modalDescription('Tandai pesanan ini sebagai sedang diproses?')
                    ->action(fn (Order $record) => $record->update(['status' => 'processing'])),
                \Filament\Actions\Action::make('verify_payment')
                    ->label('Verifikasi Bayar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record): bool => filled($record->payment_proof_image) && !$record->is_payment_verified)
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $record->payment?->update([
                            'status' => 'success',
                            'paid_at' => now(),
                        ]);

                        $record->update([
                            'status' => 'paid',
                            'is_payment_verified' => true,
                            'payment_verified_at' => now(),
                            'verified_by' => auth()->id(),
                            'paid_at' => now(),
                        ]);
                    }),
                \Filament\Actions\Action::make('ship')
                    ->label('Kirim')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record): bool => $record->status === 'processing')
                    ->form(function (Order $record) {
                        // Load relasi jika belum ter-load
                        $record->loadMissing('payment.paymentMethod');
                        $isCod = strtolower($record->payment?->paymentMethod?->code ?? '') === 'cod';

                        if ($isCod) {
                            // COD: tampilkan field resi opsional
                            return [
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Nomor Resi (opsional untuk COD)')
                                    ->maxLength(255)
                                    ->placeholder('Kosongkan jika tidak ada resi'),
                            ];
                        }

                        return [
                            Forms\Components\TextInput::make('tracking_number')
                                ->label('Nomor Resi')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Contoh: JNE1234567890'),
                        ];
                    })
                    ->modalHeading('Input Nomor Resi & Kirim')
                    ->action(function (Order $record, array $data): void {
                        $record->update([
                            'status'          => 'shipped',
                            'tracking_number' => $data['tracking_number'] ?? null,
                            'shipped_at'      => now(),
                        ]);
                        try {
                            \Illuminate\Support\Facades\Mail::to($record->user->email)
                                ->send(new \App\Mail\OrderShippedMail($record->load(['items.product', 'payment'])));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send shipped email: ' . $e->getMessage());
                        }
                    }),
                \Filament\Actions\Action::make('cancel_order')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => in_array($record->status, ['unpaid', 'paid', 'processing'], true))
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        foreach ($record->items as $item) {
                            if (!$item->parent_item_id) {
                                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                            }
                        }

                        $record->update(['status' => 'cancelled']);
                    }),
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('mark_processing')
                        ->label('Tandai: Processing')
                        ->icon('heroicon-o-cog')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => 'processing'])),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
