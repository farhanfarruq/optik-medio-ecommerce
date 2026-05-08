<?php

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommission extends EditRecord
{
    protected static string $resource = CommissionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['status'] ?? null, ['processing', 'success', 'cancelled'], true)) {
            $data['processed_by'] = auth()->id();
            $data['processed_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
