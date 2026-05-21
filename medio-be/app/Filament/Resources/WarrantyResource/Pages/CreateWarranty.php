<?php

namespace App\Filament\Resources\WarrantyResource\Pages;

use App\Filament\Resources\WarrantyResource;
use App\Models\Warranty;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateWarranty extends CreateRecord
{
    protected static string $resource = WarrantyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['warranty_number'] ?? null)) {
            $data['warranty_number'] = Warranty::generateNumber();
        }

        if (blank($data['warranty_expires_at'] ?? null) && filled($data['purchase_date'] ?? null)) {
            $data['warranty_expires_at'] = Carbon::parse($data['purchase_date'])
                ->addMonths((int) ($data['warranty_months'] ?? 12))
                ->toDateString();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
