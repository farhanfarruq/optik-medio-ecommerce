<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Complain;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->whereDate('created_at', today())
            ->sum('total_price');

        $monthRevenue = Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price');

        $totalRevenue = Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->sum('total_price');

        $pendingOrders = Order::where('status', 'paid')->count();
        $unpaidOrders = Order::where('status', 'unpaid')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $totalOrders = Order::count();
        $totalCustomers = User::where('role', 'user')->count();
        $lowStockCount = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->count();
        $pendingReturns = ReturnRequest::where('status', 'pending')->count();
        $pendingPaymentProofs = Order::whereNotNull('payment_proof_image')
            ->where('is_payment_verified', false)
            ->count();
        $openComplaints = Complain::whereIn('status', ['open', 'in_progress'])->count();

        // Trend: bandingkan pendapatan bulan ini vs bulan lalu
        $lastMonthRevenue = Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_price');
        $revenueTrend = $lastMonthRevenue > 0
            ? round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        return [
            Stat::make('Total Pendapatan', 'Rp ' . number_format((float) $totalRevenue, 0, ',', '.'))
                ->description('Dari semua pesanan berbayar')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pendapatan Bulan Ini', 'Rp ' . number_format((float) $monthRevenue, 0, ',', '.'))
                ->description(($revenueTrend >= 0 ? '▲ +' : '▼ ') . $revenueTrend . '% vs bulan lalu')
                ->descriptionIcon($revenueTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueTrend >= 0 ? 'success' : 'danger'),

            Stat::make('Pendapatan Hari Ini', 'Rp ' . number_format((float) $todayRevenue, 0, ',', '.'))
                ->description('Transaksi hari ini')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Pesanan Perlu Diproses', $pendingOrders)
                ->description('Pembayaran diterima, belum diproses')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),

            Stat::make('Pesanan Belum Dibayar', $unpaidOrders)
                ->description('Menunggu pembayaran pelanggan')
                ->descriptionIcon('heroicon-m-clock')
                ->color($unpaidOrders > 0 ? 'warning' : 'success'),

            Stat::make('Bukti Bayar Pending', $pendingPaymentProofs)
                ->description('Bukti manual belum diverifikasi')
                ->descriptionIcon('heroicon-m-receipt-refund')
                ->color($pendingPaymentProofs > 0 ? 'danger' : 'success'),

            Stat::make('Pesanan Diproses', $processingOrders)
                ->description('Sedang disiapkan untuk dikirim')
                ->descriptionIcon('heroicon-m-cog')
                ->color('primary'),

            Stat::make('Total Pesanan', $totalOrders)
                ->description('Seluruh transaksi')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Total Pelanggan', $totalCustomers)
                ->description('Akun customer terdaftar')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Stok Menipis', $lowStockCount)
                ->description('Produk aktif di bawah batas minimum')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Return Request Pending', $pendingReturns)
                ->description('Pengajuan retur menunggu respon')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($pendingReturns > 0 ? 'danger' : 'success'),

            Stat::make('Komplain Aktif', $openComplaints)
                ->description('Status open atau in progress')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($openComplaints > 0 ? 'danger' : 'success'),
        ];
    }
}
