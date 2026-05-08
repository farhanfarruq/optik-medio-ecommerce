<?php

namespace App\Filament\Resources\ComplainResource\Pages;

use App\Filament\Resources\ComplainResource;
use Filament\Resources\Pages\EditRecord;

class EditComplain extends EditRecord
{
    protected static string $resource = ComplainResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['handled_by'] = auth()->id();

        if (($data['status'] ?? null) === 'resolved' && empty($data['resolved_at'])) {
            $data['resolved_at'] = now();
        }

        return $data;
    }
}
