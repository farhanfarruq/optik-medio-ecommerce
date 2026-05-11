<?php

namespace App\Observers;

use App\Mail\ComplainResponseMail;
use App\Models\Complain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComplainObserver
{
    /**
     * Kirim notifikasi email ke customer saat admin mengupdate status atau admin_notes.
     */
    public function updated(Complain $complain): void
    {
        $statusChanged    = $complain->wasChanged('status');
        $notesChanged     = $complain->wasChanged('admin_notes');
        $notifiableStatus = in_array($complain->status, ['in_progress', 'resolved', 'rejected']);

        // Kirim email jika status berubah ke status yang perlu dinotifikasi,
        // atau jika admin_notes diisi/diubah (dan status bukan 'open')
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
