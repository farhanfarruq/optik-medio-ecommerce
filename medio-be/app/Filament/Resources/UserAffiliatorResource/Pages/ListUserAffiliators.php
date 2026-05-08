<?php

namespace App\Filament\Resources\UserAffiliatorResource\Pages;

use App\Filament\Resources\UserAffiliatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserAffiliators extends ListRecords
{
    protected static string $resource = UserAffiliatorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
