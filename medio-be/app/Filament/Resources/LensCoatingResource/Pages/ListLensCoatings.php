<?php

namespace App\Filament\Resources\LensCoatingResource\Pages;

use App\Filament\Resources\LensCoatingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLensCoatings extends ListRecords
{
    protected static string $resource = LensCoatingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
