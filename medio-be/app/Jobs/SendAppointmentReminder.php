<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    /**
     * Kirim reminder untuk appointment besok yang masih pending/confirmed.
     */
    public function handle(): void
    {
        $tomorrow = now()->addDay()->toDateString();

        $appointments = Appointment::with(['user', 'branch'])
            ->where('appointment_date', $tomorrow)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        foreach ($appointments as $appointment) {
            try {
                $email = $appointment->user?->email;
                if (! $email) {
                    continue;
                }

                Mail::to($email, $appointment->customer_name)
                    ->send(new AppointmentReminderMail($appointment));

                Log::info('Appointment reminder sent', [
                    'appointment_id' => $appointment->id,
                    'email'          => $email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send appointment reminder', [
                    'appointment_id' => $appointment->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
