<?php

namespace App\Observers;

use App\Mail\AdminNewReturnMail;
use App\Mail\ReturnResponseMail;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReturnObserver
{
    private function adminEmail(): string
    {
        return config('mail.admin_notification_email', config('mail.from.address'));
    }

    /** Notifikasi admin saat retur baru masuk. */
    public function created(ReturnRequest $returnRequest): void
    {
        try {
            $returnRequest->loadMissing(['user', 'order:id,order_number']);
            Mail::to($this->adminEmail())
                ->send(new AdminNewReturnMail($returnRequest));
            Log::info("Admin notified: new return request #{$returnRequest->id} from {$returnRequest->user->name}");
        } catch (\Exception $e) {
            Log::error("Failed to notify admin for return #{$returnRequest->id}: " . $e->getMessage());
        }
    }

    /** Notifikasi customer saat admin approve/reject retur. */
    public function updated(ReturnRequest $returnRequest): void
    {
        if ($returnRequest->wasChanged('status') && in_array($returnRequest->status, ['approved', 'rejected'])) {
            try {
                $returnRequest->loadMissing(['user', 'order:id,order_number']);
                Mail::to($returnRequest->user->email)->send(new ReturnResponseMail($returnRequest));
                Log::info("Return response email sent to {$returnRequest->user->email} for return #{$returnRequest->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send return response email for return #{$returnRequest->id}: " . $e->getMessage());
            }
        }
    }
}
