<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueMonitorWidget extends BaseWidget
{
    protected static ?int $sort = 7;
    protected ?string $heading = 'Queue & Job Monitor';

    protected function getStats(): array
    {
        // Failed jobs
        $failedJobs = DB::table('failed_jobs')->count();

        // Failed jobs dalam 24 jam terakhir
        $recentFailed = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay()->toDateTimeString())
            ->count();

        // Pending jobs di queue
        $pendingJobs = DB::table('jobs')->count();

        // Webhook events yang gagal diproses
        $failedWebhooks = DB::table('webhook_event_logs')
            ->where('processing_status', 'failed')
            ->count();

        // Webhook events yang belum diproses (received tapi belum processed)
        $pendingWebhooks = DB::table('webhook_event_logs')
            ->where('processing_status', 'received')
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();

        return [
            Stat::make('Failed Jobs (Total)', $failedJobs)
                ->description('Semua job yang gagal')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),

            Stat::make('Failed Jobs (24 Jam)', $recentFailed)
                ->description('Job gagal dalam 24 jam terakhir')
                ->descriptionIcon('heroicon-m-clock')
                ->color($recentFailed > 0 ? 'warning' : 'success'),

            Stat::make('Pending Jobs', $pendingJobs)
                ->description('Job menunggu diproses')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($pendingJobs > 10 ? 'warning' : 'success'),

            Stat::make('Webhook Gagal', $failedWebhooks)
                ->description('Webhook tidak berhasil diproses')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedWebhooks > 0 ? 'danger' : 'success'),

            Stat::make('Webhook Pending', $pendingWebhooks)
                ->description('Webhook diterima >5 menit lalu, belum diproses')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($pendingWebhooks > 0 ? 'warning' : 'success'),
        ];
    }
}
