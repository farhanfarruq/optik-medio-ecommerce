<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Filament\Actions;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify_payment')
                ->label('Setujui Pembayaran')
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
            Actions\Action::make('update_tracking')
                ->label('Input / Update Resi')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn (Order $record): bool => in_array($record->status, ['processing', 'shipped'], true))
                ->form([
                    \Filament\Forms\Components\TextInput::make('tracking_number')
                        ->label('Nomor Resi')
                        ->required()
                        ->default(fn (Order $record) => $record->tracking_number),
                ])
                ->action(function (Order $record, array $data): void {
                    $payload = [
                        'tracking_number' => $data['tracking_number'],
                    ];

                    if ($record->status === 'processing') {
                        $payload['status'] = 'shipped';
                        $payload['shipped_at'] = now();
                    }

                    $record->update($payload);
                }),
            Actions\Action::make('cancel_order')
                ->label('Batalkan Pesanan')
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
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Pesanan')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->components([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Nomor Order')
                            ->copyable()
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
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
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Tanggal Pesan')
                            ->dateTime('d M Y, H:i'),
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('shipping_cost')
                            ->label('Ongkos Kirim')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('total_price')
                            ->label('Total Bayar')
                            ->money('IDR')
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('promo.name')
                            ->label('Promo Eksklusif')
                            ->placeholder('Tidak ada promo'),
                        Infolists\Components\TextEntry::make('promo_discount_amount')
                            ->label('Diskon Promo')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('discount.code')
                            ->label('Kode Diskon')
                            ->placeholder('Tidak ada diskon'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Potongan Diskon')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('courier')
                            ->label('Kurir'),
                        Infolists\Components\TextEntry::make('courier_service')
                            ->label('Layanan'),
                        Infolists\Components\TextEntry::make('tracking_number')
                            ->label('Nomor Resi')
                            ->copyable()
                            ->placeholder('Belum ada resi'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Dibayar Pada')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('shipped_at')
                            ->label('Dikirim Pada')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('delivered_at')
                            ->label('Diterima Pada')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan Customer')
                            ->placeholder('Tidak ada catatan')
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Data Pelanggan')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->components([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Nama'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('user.loyalty_points')
                            ->label('Poin Loyalitas'),
                    ]),

                \Filament\Schemas\Components\Section::make('Alamat Pengiriman')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->components([
                        Infolists\Components\TextEntry::make('shippingAddress.recipient_name')
                            ->label('Penerima'),
                        Infolists\Components\TextEntry::make('shippingAddress.phone')
                            ->label('Telepon'),
                        Infolists\Components\TextEntry::make('shippingAddress.address')
                            ->label('Alamat')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('shippingAddress.district')
                            ->label('Kecamatan'),
                        Infolists\Components\TextEntry::make('shippingAddress.city')
                            ->label('Kota'),
                        Infolists\Components\TextEntry::make('shippingAddress.province')
                            ->label('Provinsi'),
                        Infolists\Components\TextEntry::make('shippingAddress.postal_code')
                            ->label('Kode Pos'),
                    ]),

                \Filament\Schemas\Components\Section::make('Pembayaran')
                    ->icon('heroicon-o-credit-card')
                    ->columns(3)
                    ->components([
                        Infolists\Components\TextEntry::make('payment.transaction_id')
                            ->label('Transaction ID')
                            ->copyable()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.payment_method')
                            ->label('Metode Bayar')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.paymentMethod.name')
                            ->label('Metode Internal')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.status')
                            ->label('Status Bayar')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success',
                                'pending' => 'warning',
                                'failed'  => 'danger',
                                default   => 'gray',
                            })
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.gross_amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.paid_at')
                            ->label('Waktu Bayar')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment.checkout_url')
                            ->label('URL Pembayaran')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('bank.name')
                            ->label('Bank Tujuan')
                            ->placeholder('-'),
                        Infolists\Components\IconEntry::make('is_payment_verified')
                            ->label('Terverifikasi')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('verifiedBy.name')
                            ->label('Diverifikasi Oleh')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment_verified_at')
                            ->label('Waktu Verifikasi')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                    ]),

                \Filament\Schemas\Components\Section::make('Bukti Pembayaran & Riwayat')
                    ->icon('heroicon-o-document-text')
                    ->components([
                        Infolists\Components\TextEntry::make('payment_proof_image')
                            ->label('Bukti Transfer')
                            ->formatStateUsing(fn ($state) => $state ? 'Lihat Bukti Transfer' : '-')
                            ->url(fn ($state) => $state ? asset('storage/' . $state) : null)
                            ->openUrlInNewTab(),
                        Infolists\Components\RepeatableEntry::make('logs')
                            ->label('Riwayat Log Pesanan')
                            ->components([
                                Infolists\Components\TextEntry::make('title')->weight('bold'),
                                Infolists\Components\TextEntry::make('description')->placeholder('-'),
                                Infolists\Components\TextEntry::make('current_status')->label('Status')->badge(),
                                Infolists\Components\TextEntry::make('actedBy.name')->label('Oleh')->placeholder('Sistem'),
                                Infolists\Components\TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y, H:i'),
                            ])
                            ->columns(5),
                    ]),

                \Filament\Schemas\Components\Section::make('Item Pesanan')
                    ->icon('heroicon-o-shopping-bag')
                    ->components([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->components([
                                Infolists\Components\TextEntry::make('product_name')
                                    ->label('Produk')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty'),
                                Infolists\Components\TextEntry::make('product_price')
                                    ->label('Harga Satuan')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
