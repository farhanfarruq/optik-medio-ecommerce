<?php

namespace App\Filament\Resources\PrescriptionProfileResource\Pages;

use App\Filament\Resources\PrescriptionProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrescriptionProfile extends EditRecord
{
    protected static string $resource = PrescriptionProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
