<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Filament\Actions;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
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
                    $record->payment?->update(['status' => 'success', 'paid_at' => now()]);
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
                ->form(function (Order $record) {
                    $record->loadMissing('payment.paymentMethod');
                    $isCod = strtolower($record->payment?->paymentMethod?->code ?? '') === 'cod';

                    return [
                        \Filament\Forms\Components\TextInput::make('tracking_number')
                            ->label($isCod ? 'Nomor Resi (opsional untuk COD)' : 'Nomor Resi')
                            ->required(!$isCod)
                            ->default(fn (Order $record) => $record->tracking_number)
                            ->maxLength(255)
                            ->placeholder($isCod ? 'Kosongkan jika tidak ada resi' : 'Contoh: JNE1234567890'),
                    ];
                })
                ->action(function (Order $record, array $data): void {
                    $payload = ['tracking_number' => $data['tracking_number']];
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
        return $schema->components([

            // ── Baris 1: Info Pesanan (kiri) + Rincian Harga (kanan) ──────────
            Grid::make(2)->schema([

                \Filament\Schemas\Components\Section::make('Informasi Pesanan')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Nomor Order')->copyable()->weight('bold'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'unpaid' => 'warning', 'paid' => 'info', 'processing' => 'primary',
                                'shipped' => 'info', 'delivered' => 'success',
                                'cancelled' => 'danger', 'refunded' => 'gray', default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Tanggal Pesan')->dateTime('d M Y, H:i'),
                        Infolists\Components\TextEntry::make('courier')
                            ->label('Kurir'),
                        Infolists\Components\TextEntry::make('courier_service')
                            ->label('Layanan'),
                        Infolists\Components\TextEntry::make('tracking_number')
                            ->label('Nomor Resi')->copyable()->placeholder('—'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Dibayar')->dateTime('d M Y, H:i')->placeholder('—'),
                        Infolists\Components\TextEntry::make('shipped_at')
                            ->label('Dikirim')->dateTime('d M Y, H:i')->placeholder('—'),
                        Infolists\Components\TextEntry::make('delivered_at')
                            ->label('Diterima')->dateTime('d M Y, H:i')->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')->placeholder('—')->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Rincian Harga')
                    ->icon('heroicon-o-calculator')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')->money('IDR'),
                        Infolists\Components\TextEntry::make('shipping_cost')
                            ->label('Ongkos Kirim')->money('IDR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Potongan Kupon')->money('IDR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('promo_discount_amount')
                            ->label('Potongan Promo')->money('IDR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('level_discount_amount')
                            ->label('Diskon Member')->money('IDR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('loyalty_discount_amount')
                            ->label('Potongan Poin')->money('IDR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('loyalty_points_used')
                            ->label('Poin Dipakai')
                            ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 0, ',', '.') . ' poin' : '—'),
                        Infolists\Components\TextEntry::make('loyalty_points_earned')
                            ->label('Poin Diperoleh')
                            ->formatStateUsing(fn ($state) => $state > 0 ? '+' . number_format($state, 0, ',', '.') . ' poin' : '—')
                            ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),
                        Infolists\Components\TextEntry::make('total_price')
                            ->label('TOTAL DIBAYAR')->money('IDR')
                            ->weight('bold')->size('lg')->color('success')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Kalkulasi')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state, Order $record) {
                                $parts = [
                                    'Subtotal: Rp ' . number_format($record->subtotal, 0, ',', '.'),
                                    '+ Ongkir: Rp ' . number_format($record->shipping_cost, 0, ',', '.'),
                                ];
                                if ($record->discount_amount > 0)
                                    $parts[] = '- Kupon: Rp ' . number_format($record->discount_amount, 0, ',', '.');
                                if ($record->promo_discount_amount > 0)
                                    $parts[] = '- Promo: Rp ' . number_format($record->promo_discount_amount, 0, ',', '.');
                                if ($record->level_discount_amount > 0)
                                    $parts[] = '- Member: Rp ' . number_format($record->level_discount_amount, 0, ',', '.');
                                if ($record->loyalty_discount_amount > 0)
                                    $parts[] = '- Poin (' . $record->loyalty_points_used . '): Rp ' . number_format($record->loyalty_discount_amount, 0, ',', '.');
                                $parts[] = '= Total: Rp ' . number_format($record->total_price, 0, ',', '.');
                                return implode('  ·  ', $parts);
                            }),
                    ]),
            ]),

            // ── Baris 2: Data Pelanggan (kiri) + Alamat Pengiriman (kanan) ────
            Grid::make(2)->schema([

                \Filament\Schemas\Components\Section::make('Data Pelanggan')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')->label('Nama'),
                        Infolists\Components\TextEntry::make('user.email')->label('Email')->copyable(),
                        Infolists\Components\TextEntry::make('user.phone')->label('Telepon')->placeholder('—'),
                        Infolists\Components\TextEntry::make('user.loyalty_points')
                            ->label('Saldo Poin')
                            ->formatStateUsing(fn ($state) => number_format($state ?? 0, 0, ',', '.') . ' poin'),
                    ]),

                \Filament\Schemas\Components\Section::make('Alamat Pengiriman')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('shippingAddress.recipient_name')->label('Penerima'),
                        Infolists\Components\TextEntry::make('shippingAddress.phone')->label('Telepon'),
                        Infolists\Components\TextEntry::make('shippingAddress.address')
                            ->label('Alamat')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('shippingAddress.district')->label('Kecamatan'),
                        Infolists\Components\TextEntry::make('shippingAddress.city')->label('Kota'),
                        Infolists\Components\TextEntry::make('shippingAddress.province')->label('Provinsi'),
                        Infolists\Components\TextEntry::make('shippingAddress.postal_code')->label('Kode Pos'),
                    ]),
            ]),

            // ── Baris 3: Pembayaran (kiri) + Bukti & Riwayat (kanan) ─────────
            Grid::make(2)->schema([

                \Filament\Schemas\Components\Section::make('Pembayaran')
                    ->icon('heroicon-o-credit-card')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('payment.paymentMethod.name')
                            ->label('Metode')->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment.status')
                            ->label('Status Bayar')->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success', 'pending' => 'warning',
                                'failed' => 'danger', default => 'gray',
                            })->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment.gross_amount')
                            ->label('Jumlah Tagihan')->money('IDR')->placeholder('—'),
                        Infolists\Components\TextEntry::make('bank.name')
                            ->label('Bank Tujuan')->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_payment_verified')
                            ->label('Terverifikasi')->boolean(),
                        Infolists\Components\TextEntry::make('payment_verified_at')
                            ->label('Waktu Verifikasi')->dateTime('d M Y, H:i')->placeholder('—'),
                        Infolists\Components\TextEntry::make('verifiedBy.name')
                            ->label('Diverifikasi Oleh')->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment.transaction_id')
                            ->label('Transaction ID')->copyable()->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_proof_image')
                            ->label('Bukti Transfer')
                            ->formatStateUsing(fn ($state) => $state ? '📎 Lihat Bukti' : '—')
                            ->url(fn ($state) => $state ? asset('storage/' . $state) : null)
                            ->openUrlInNewTab(),
                        Infolists\Components\TextEntry::make('payment.checkout_url')
                            ->label('URL Xendit')
                            ->formatStateUsing(fn ($state) => $state ? '🔗 Buka URL' : '—')
                            ->url(fn ($state) => $state)->openUrlInNewTab(),
                    ]),

                \Filament\Schemas\Components\Section::make('Riwayat Log')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('logs')
                            ->label('')
                            ->columns(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('title')->weight('bold'),
                                Infolists\Components\TextEntry::make('current_status')
                                    ->label('Status')->badge(),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Waktu')->dateTime('d M Y, H:i'),
                                Infolists\Components\TextEntry::make('description')
                                    ->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),
            ]),

            // ── Baris 4: Item Pesanan (full width) ───────────────────────────
            \Filament\Schemas\Components\Section::make('Item Pesanan')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')
                                ->label('Produk')->weight('bold'),
                            Infolists\Components\TextEntry::make('quantity')->label('Qty'),
                            Infolists\Components\TextEntry::make('product_price')
                                ->label('Harga Satuan')->money('IDR'),
                            Infolists\Components\TextEntry::make('subtotal')
                                ->label('Subtotal')->money('IDR'),
                        ]),
                ]),
        ]);
    }
}
