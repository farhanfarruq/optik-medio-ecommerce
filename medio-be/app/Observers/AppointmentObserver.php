<?php

namespace App\Observers;

use App\Mail\AdminNewAppointmentMail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentObserver
{
    private function adminEmail(): string
    {
        return config('mail.admin_notification_email', config('mail.from.address'));
    }

    public function created(Appointment $appointment): void
    {
        try {
            $appointment->loadMissing('user');
            Mail::to($this->adminEmail())
                ->send(new AdminNewAppointmentMail($appointment));
            Log::info("Admin notified: new appointment #{$appointment->id} from {$appointment->customer_name}");
        } catch (\Exception $e) {
            Log::error("Failed to notify admin for appointment #{$appointment->id}: " . $e->getMessage());
        }
    }
}
