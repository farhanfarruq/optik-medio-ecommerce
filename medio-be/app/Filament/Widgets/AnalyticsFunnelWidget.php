<?php

namespace App\Filament\Widgets;

use App\Models\BusinessEvent;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AnalyticsFunnelWidget extends ChartWidget
{
    protected ?string $heading = '📊 Funnel Konversi (7 Hari Terakhir)';
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $since = now()->subDays(7);

        $steps = [
            'Lihat Produk'     => BusinessEvent::where('event_type', BusinessEvent::PRODUCT_VIEWED)->where('created_at', '>=', $since)->count(),
            'Tambah Keranjang' => BusinessEvent::where('event_type', BusinessEvent::ADD_TO_CART)->where('created_at', '>=', $since)->count(),
            'Mulai Checkout'   => BusinessEvent::where('event_type', BusinessEvent::CHECKOUT_STARTED)->where('created_at', '>=', $since)->count(),
            'Pilih Pengiriman' => BusinessEvent::where('event_type', BusinessEvent::SHIPPING_SELECTED)->where('created_at', '>=', $since)->count(),
            'Pilih Pembayaran' => BusinessEvent::where('event_type', BusinessEvent::PAYMENT_SELECTED)->where('created_at', '>=', $since)->count(),
            'Order Dibuat'     => BusinessEvent::where('event_type', BusinessEvent::ORDER_CREATED)->where('created_at', '>=', $since)->count(),
            'Pembayaran Sukses'=> BusinessEvent::where('event_type', BusinessEvent::PAYMENT_SUCCESS)->where('created_at', '>=', $since)->count(),
        ];

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah Event',
                    'data'            => array_values($steps),
                    'backgroundColor' => [
                        'rgba(193,154,81,0.8)',
                        'rgba(193,154,81,0.7)',
                        'rgba(193,154,81,0.6)',
                        'rgba(193,154,81,0.5)',
                        'rgba(193,154,81,0.4)',
                        'rgba(193,154,81,0.3)',
                        'rgba(22,163,74,0.7)',
                    ],
                    'borderColor' => 'rgba(193,154,81,1)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_keys($steps),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
