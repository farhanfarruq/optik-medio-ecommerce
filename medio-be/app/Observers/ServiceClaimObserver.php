<?php

namespace App\Observers;

use App\Mail\AdminNewServiceClaimMail;
use App\Models\ServiceClaim;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ServiceClaimObserver
{
    private function adminEmail(): string
    {
        return config('mail.admin_notification_email', config('mail.from.address'));
    }

    public function created(ServiceClaim $claim): void
    {
        try {
            $claim->loadMissing(['user', 'warranty']);
            Mail::to($this->adminEmail())
                ->send(new AdminNewServiceClaimMail($claim));
            Log::info("Admin notified: new service claim #{$claim->claim_number}");
        } catch (\Exception $e) {
            Log::error("Failed to notify admin for service claim #{$claim->id}: " . $e->getMessage());
        }
    }
}
