<?php

namespace App\Console;

use App\Jobs\SendAbandonedCartReminder;
use App\Jobs\SendAppointmentReminder;
use App\Jobs\SendReviewRequest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Kirim reminder abandoned cart setiap jam
        $schedule->job(new SendAbandonedCartReminder())
            ->hourly()
            ->withoutOverlapping()
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('SendAbandonedCartReminder job failed.'));

        // Kirim reminder appointment besok — setiap hari jam 18:00
        $schedule->job(new SendAppointmentReminder())
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('SendAppointmentReminder job failed.'));

        // Kirim review request untuk order delivered 3 hari lalu — setiap hari jam 10:00
        $schedule->job(new SendReviewRequest())
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('SendReviewRequest job failed.'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
