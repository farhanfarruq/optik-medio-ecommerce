<?php

namespace App\Filament\Resources\ProductCompatibilityResource\Pages;

use App\Filament\Resources\ProductCompatibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCompatibility extends EditRecord
{
    protected static string $resource = ProductCompatibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
