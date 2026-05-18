<?php

namespace App\Observers;

use App\Mail\AdminNewComplainMail;
use App\Mail\ComplainResponseMail;
use App\Models\Complain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComplainObserver
{
    private function adminEmail(): string
    {
        return config('mail.admin_notification_email', config('mail.from.address'));
    }

    /** Notifikasi admin saat komplain baru masuk. */
    public function created(Complain $complain): void
    {
        try {
            $complain->loadMissing(['user', 'order:id,order_number']);
            Mail::to($this->adminEmail())
                ->send(new AdminNewComplainMail($complain));
            Log::info("Admin notified: new complain #{$complain->id} from {$complain->user->name}");
        } catch (\Exception $e) {
            Log::error("Failed to notify admin for complain #{$complain->id}: " . $e->getMessage());
        }
    }

    /** Notifikasi customer saat admin mengupdate status atau admin_notes. */
    public function updated(Complain $complain): void
    {
        $statusChanged    = $complain->wasChanged('status');
        $notesChanged     = $complain->wasChanged('admin_notes');
        $notifiableStatus = in_array($complain->status, ['in_progress', 'resolved', 'rejected']);

        if (($statusChanged && $notifiableStatus) || ($notesChanged && $complain->admin_notes && $notifiableStatus)) {
            try {
                $complain->loadMissing(['user', 'order:id,order_number']);
                Mail::to($complain->user->email)->send(new ComplainResponseMail($complain));
                Log::info("Complain response email sent to {$complain->user->email} for complain #{$complain->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send complain response email for complain #{$complain->id}: " . $e->getMessage());
            }
        }
    }
}
