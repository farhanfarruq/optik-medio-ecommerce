<?php

namespace App\Filament\Resources\StoreCloseResource\Pages;

use App\Filament\Resources\StoreCloseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreCloses extends ListRecords
{
    protected static string $resource = StoreCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
