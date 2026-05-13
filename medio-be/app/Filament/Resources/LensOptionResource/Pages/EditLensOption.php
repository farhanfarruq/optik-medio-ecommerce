<?php

namespace App\Filament\Resources\LensOptionResource\Pages;

use App\Filament\Resources\LensOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLensOption extends EditRecord
{
    protected static string $resource = LensOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
