<?php

namespace App\Filament\Resources\LensOptionResource\Pages;

use App\Filament\Resources\LensOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLensOptions extends ListRecords
{
    protected static string $resource = LensOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
