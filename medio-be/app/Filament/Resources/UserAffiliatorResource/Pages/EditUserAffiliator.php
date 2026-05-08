<?php

namespace App\Filament\Resources\UserAffiliatorResource\Pages;

use App\Filament\Resources\UserAffiliatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAffiliator extends EditRecord
{
    protected static string $resource = UserAffiliatorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
