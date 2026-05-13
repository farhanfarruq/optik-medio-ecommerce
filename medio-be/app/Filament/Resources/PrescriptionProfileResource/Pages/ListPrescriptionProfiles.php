<?php

namespace App\Filament\Resources\PrescriptionProfileResource\Pages;

use App\Filament\Resources\PrescriptionProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrescriptionProfiles extends ListRecords
{
    protected static string $resource = PrescriptionProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
