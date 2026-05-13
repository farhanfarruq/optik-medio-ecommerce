<?php

namespace App\Filament\Resources\ServiceClaimResource\Pages;

use App\Filament\Resources\ServiceClaimResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceClaim extends EditRecord
{
    protected static string $resource = ServiceClaimResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['status'] ?? null, ['completed', 'rejected'], true) && empty($data['resolved_at'])) {
            $data['resolved_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ServiceClaimResource::syncWarrantyStatus($this->getRecord());
    }
}
