<?php

namespace App\Observers;

use App\Mail\ReturnResponseMail;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReturnObserver
{
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
