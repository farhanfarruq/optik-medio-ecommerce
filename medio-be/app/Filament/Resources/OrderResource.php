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
    protected static ?string $navigationLabel = 'Pesanan';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $needsAction = \App\Models\Order::query()
            ->where(function ($q) {
                $q->whereIn('status', ['unpaid', 'paid'])
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('payment_proof_image')
                         ->where('is_payment_verified', false);
                  });
            })
            ->count();

        return $needsAction > 0 ? (string) $needsAction : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    protected static function statusFilterOptions(): array
    {
        $icons = [
            'unpaid'                      => '⏳',
            'paid'                        => '💰',
            'waiting_prescription_review' => '📝',
            'prescription_verified'       => '🔎',
            'lens_processing'             => '🧪',
            'processing'                  => '🔄',
            'shipped'                     => '🚚',
            'delivered'                   => '✅',
            'completed'                   => '✅',
            'cancelled'                   => '❌',
            'refunded'                    => '↩️',
        ];

        return collect(Order::statusOptions())
            ->mapWithKeys(fn (string $label, string $status): array => [
                $status => ($icons[$status] ?? '•') . ' ' . $label,
            ])
            ->all();
    }

    protected static function updateOrderStatus(Order $record, string $status): void
    {
        if (! Order::hasStatus($status) || $record->status === $status) {
            return;
        }

        if ($status === 'cancelled' && in_array($record->status, ['unpaid', 'paid', 'processing'], true)) {
            foreach ($record->items as $item) {
                if (! $item->parent_item_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
        }

        $record->update([
            'status' => $status,
            ...Order::statusTimestampPayload($status),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('status')
                    ->options(Order::statusOptions())
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
                Forms\Components\Toggle::make('shipping_protection_opted')
                    ->label('Proteksi Pengiriman')
                    ->disabled(),
                Forms\Components\TextInput::make('shipping_protection_fee')
                    ->label('Biaya Proteksi Pengiriman')
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
                                $record->loadMissing('items.lensOption', 'items.lensCoating', 'items.prescriptionProfile');

                                $itemsHtml = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                                foreach ($record->items as $item) {
                                    $snapshot = $item->configuration_snapshot ?? [];
                                    $lensOption = $snapshot['lens_option']['name'] ?? $item->lensOption?->name;
                                    $lensPrice = $snapshot['lens_option']['base_price'] ?? $item->lens_price ?? 0;
                                    $coating = $snapshot['lens_coating']['name'] ?? $item->lensCoating?->name;
                                    $coatingPrice = $snapshot['lens_coating']['price'] ?? $item->coating_price ?? 0;
                                    $prescriptionLabel = $item->prescriptionProfile
                                        ? 'Profil #' . $item->prescriptionProfile->id . ' - ' . e($item->prescriptionProfile->label)
                                        : (!empty($snapshot['prescription']) ? 'Input manual' : null);

                                    $itemsHtml .= "<div style='border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #ffffff;'>";
                                    $itemsHtml .= "<div style='font-weight: 700; color: #111827;'>" . e($item->quantity) . "x " . e($item->product_name) . "</div>";
                                    $itemsHtml .= "<div style='font-size: 12px; color: #6b7280;'>Harga item: Rp " . number_format((float) $item->product_price, 0, ',', '.') . " | Subtotal: Rp " . number_format((float) $item->subtotal, 0, ',', '.') . "</div>";

                                    if ($lensOption || $coating || $prescriptionLabel) {
                                        $itemsHtml .= "<div style='margin-top: 8px; padding: 8px; background: #f9fafb; border-radius: 6px; font-size: 12px; color: #374151;'>";
                                        if ($lensOption) {
                                            $itemsHtml .= "<div><b>Lensa:</b> " . e($lensOption) . " (+Rp " . number_format((float) $lensPrice, 0, ',', '.') . ")</div>";
                                        }
                                        if ($coating) {
                                            $itemsHtml .= "<div><b>Coating:</b> " . e($coating) . " (+Rp " . number_format((float) $coatingPrice, 0, ',', '.') . ")</div>";
                                        }
                                        if ($prescriptionLabel) {
                                            $itemsHtml .= "<div><b>Resep:</b> {$prescriptionLabel}</div>";
                                        }
                                        $itemsHtml .= '</div>';
                                    }

                                    $itemsHtml .= '</div>';
                                }
                                $itemsHtml .= '</div>';
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_channel_label')
                    ->label('Metode Pembayaran')
                    ->getStateUsing(function (Order $record): string {
                        // Prioritas 1: kolom payment_channel yang sudah di-backfill
                        if (filled($record->payment_channel)) {
                            return $record->payment_channel;
                        }

                        // Prioritas 2: transfer manual via bank_id
                        if ($record->bank_id && $record->bank) {
                            return $record->bank->name;
                        }

                        // Prioritas 3: dari relasi payment → paymentMethod
                        $record->loadMissing('payment.paymentMethod');
                        $methodCode = strtolower($record->payment?->paymentMethod?->code ?? '');
                        $methodName = $record->payment?->paymentMethod?->name ?? '';
                        $provider   = strtolower($record->payment?->provider ?? '');

                        if ($methodCode === 'cod') {
                            return 'COD';
                        }

                        if (str_contains($methodCode, 'xendit') || $provider === 'xendit') {
                            return 'Xendit';
                        }

                        if ($methodName) {
                            return $methodName;
                        }

                        return '—';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'COD'                  => 'warning',
                        str_starts_with($state, 'Xendit') => 'info',
                        $state === '—'                    => 'gray',
                        default                           => 'success', // bank transfer
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->where('payment_channel', 'like', "%{$search}%")
                              ->orWhereHas('bank', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('payment.paymentMethod', fn ($q) => $q->where('name', 'like', "%{$search}%")
                                  ->orWhere('code', 'like', "%{$search}%"));
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('items.product_name')
                    ->label('Produk')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('shipping_protection_opted')
                    ->label('Proteksi')
                    ->boolean(),
                Tables\Columns\TextColumn::make('total_price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('fulfillment_method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'store_pickup' => 'success',
                        default        => 'info',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'store_pickup' => 'heroicon-o-building-storefront',
                        default        => 'heroicon-o-truck',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'store_pickup' => 'Ambil di Toko',
                        default        => 'Dikirim',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid'                      => 'warning',
                        'paid'                        => 'info',
                        'waiting_prescription_review' => 'warning',
                        'prescription_verified'       => 'info',
                        'lens_processing'             => 'primary',
                        'processing'                  => 'primary',
                        'shipped'                     => 'info',
                        'delivered', 'completed'      => 'success',
                        'cancelled'                   => 'danger',
                        'refunded'                    => 'gray',
                        default                       => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'unpaid'                      => 'heroicon-o-clock',
                        'paid'                        => 'heroicon-o-banknotes',
                        'waiting_prescription_review' => 'heroicon-o-document-magnifying-glass',
                        'prescription_verified'       => 'heroicon-o-shield-check',
                        'lens_processing'             => 'heroicon-o-cog-6-tooth',
                        'processing'                  => 'heroicon-o-cog',
                        'shipped'                     => 'heroicon-o-truck',
                        'delivered', 'completed'      => 'heroicon-o-check-circle',
                        'cancelled'                   => 'heroicon-o-x-circle',
                        'refunded'                    => 'heroicon-o-arrow-uturn-left',
                        default                       => 'heroicon-o-question-mark-circle',
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
                    ->options(self::statusFilterOptions())
                    ->multiple()
                    ->label('Filter Status'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Pelanggan')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('payment_method_filter')
                    ->label('Metode Pembayaran')
                    ->options(function () {
                        $options = [];
                        // Bank transfer manual
                        \App\Models\Bank::where('is_active', true)->orderBy('name')->each(function ($bank) use (&$options) {
                            $options['bank_' . $bank->id] = '🏦 ' . $bank->name;
                        });
                        // COD & Xendit dari payment_methods
                        \App\Models\PaymentMethod::where('is_active', true)->orderBy('sort_order')->each(function ($pm) use (&$options) {
                            if (strtolower($pm->code) === 'cod') {
                                $options['pm_' . $pm->id] = '🚚 COD';
                            } elseif (str_contains(strtolower($pm->code), 'xendit')) {
                                $options['pm_' . $pm->id] = '💳 Xendit — ' . $pm->name;
                            } else {
                                $options['pm_' . $pm->id] = '💳 ' . $pm->name;
                            }
                        });
                        return $options;
                    })
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) return;
                        if (str_starts_with($value, 'bank_')) {
                            $bankId = (int) substr($value, 5);
                            $query->where('bank_id', $bankId);
                        } elseif (str_starts_with($value, 'pm_')) {
                            $pmId = (int) substr($value, 3);
                            $query->whereHas('payment', fn ($q) => $q->where('payment_method_id', $pmId));
                        }
                    }),
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
                \Filament\Actions\Action::make('update_status')
                    ->label('Ubah Status')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Status Pesanan')
                            ->options(Order::statusOptions())
                            ->default(fn (Order $record): string => $record->status)
                            ->required(),
                    ])
                    ->action(fn (Order $record, array $data) => self::updateOrderStatus($record, $data['status'])),
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
                \Filament\Actions\Action::make('send_notification')
                    ->label('Kirim Notifikasi')
                    ->icon('heroicon-o-bell')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Select::make('notification_type')
                            ->label('Jenis Notifikasi')
                            ->options([
                                'order_update'   => 'Update Status Pesanan',
                                'payment_remind' => 'Pengingat Pembayaran',
                                'custom'         => 'Pesan Kustom',
                            ])
                            ->required()
                            ->live(),
                        \Filament\Forms\Components\Textarea::make('custom_message')
                            ->label('Pesan Kustom')
                            ->rows(3)
                            ->placeholder('Tulis pesan untuk pelanggan...')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('notification_type') === 'custom')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('notification_type') === 'custom')
                            ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('notification_type') === 'custom'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            $type    = $data['notification_type'];
                            $message = trim((string) ($data['custom_message'] ?? ''));
                            $sent    = false;

                            if ($type === 'order_update') {
                                $eventType = match ($record->status) {
                                    'processing', 'cancelled', 'delivered', 'completed' => $record->status,
                                    'paid' => $record->is_payment_verified ? 'payment_verified' : 'paid',
                                    default => $record->status,
                                };

                                \Illuminate\Support\Facades\Mail::to($record->user->email)
                                    ->send(new \App\Mail\OrderStatusMail($record, $eventType));
                                $sent = true;
                            } elseif ($type === 'payment_remind' && $record->status === 'unpaid') {
                                \Illuminate\Support\Facades\Mail::raw(
                                    "Halo {$record->user->name},\n\nPesanan #{$record->order_number} Anda belum dibayar. Segera selesaikan pembayaran sebelum pesanan dibatalkan.\n\nTerima kasih,\nOptik Medio",
                                    fn ($m) => $m->to($record->user->email)->subject("Pengingat Pembayaran #{$record->order_number}")
                                );
                                $sent = true;
                            } elseif ($type === 'custom' && $message !== '') {
                                \Illuminate\Support\Facades\Mail::to($record->user->email)
                                    ->send(new \App\Mail\OrderCustomNotificationMail($record, $message));
                                $sent = true;
                            }

                            if (!$sent) {
                                throw new \RuntimeException('Jenis notifikasi tidak valid untuk status pesanan ini.');
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Notifikasi berhasil dikirim ke ' . $record->user->email)
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Gagal mengirim notifikasi: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_update_status')
                        ->label('Ubah Status Pesanan')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('gray')
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status Pesanan')
                                ->options(Order::statusOptions())
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                self::updateOrderStatus($record, $data['status']);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('mark_processing')
                        ->label('Tandai: Processing')
                        ->icon('heroicon-o-cog')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => 'processing'])),
                    \Filament\Actions\BulkAction::make('bulk_update_tracking')
                        ->label('Update Resi (Bulk)')
                        ->icon('heroicon-o-truck')
                        ->color('info')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('tracking_data')
                                ->label('Data Resi (satu baris per order)')
                                ->helperText('Format: ORDER_NUMBER|TRACKING_NUMBER — satu baris per pesanan')
                                ->rows(6)
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            $lines = array_filter(array_map('trim', explode("\n", $data['tracking_data'])));
                            $map   = [];
                            foreach ($lines as $line) {
                                [$orderNum, $tracking] = array_pad(explode('|', $line, 2), 2, '');
                                if ($orderNum && $tracking) {
                                    $map[trim($orderNum)] = trim($tracking);
                                }
                            }
                            foreach ($records as $record) {
                                if (isset($map[$record->order_number])) {
                                    $record->update([
                                        'tracking_number' => $map[$record->order_number],
                                        'status'          => 'shipped',
                                        'shipped_at'      => now(),
                                    ]);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('mark_shipped')
                        ->label('Tandai: Dikirim')
                        ->icon('heroicon-o-truck')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update([
                            'status'     => 'shipped',
                            'shipped_at' => now(),
                        ])),
                    \Filament\Actions\BulkAction::make('mark_cancelled')
                        ->label('Batalkan Pesanan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if (in_array($record->status, ['unpaid', 'paid', 'processing'], true)) {
                                    foreach ($record->items as $item) {
                                        if (! $item->parent_item_id) {
                                            \App\Models\Product::where('id', $item->product_id)
                                                ->increment('stock', $item->quantity);
                                        }
                                    }
                                    $record->update(['status' => 'cancelled']);
                                }
                            }
                        }),
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
