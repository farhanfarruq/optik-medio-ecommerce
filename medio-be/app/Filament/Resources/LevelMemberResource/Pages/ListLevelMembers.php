<?php

namespace App\Filament\Resources\LevelMemberResource\Pages;

use App\Filament\Resources\LevelMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLevelMembers extends ListRecords
{
    protected static string $resource = LevelMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
