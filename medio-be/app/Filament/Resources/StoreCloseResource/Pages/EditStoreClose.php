<?php

namespace App\Filament\Resources\StoreCloseResource\Pages;

use App\Filament\Resources\StoreCloseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreClose extends EditRecord
{
    protected static string $resource = StoreCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
