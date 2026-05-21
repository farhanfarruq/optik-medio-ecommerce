<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Pisahkan lens option IDs sebelum update (bukan kolom di tabel products)
        $lensOptionIds = $data['compatible_lens_option_ids'] ?? null;
        unset($data['compatible_lens_option_ids']);

        $record->update($data);

        // Sync relasi BelongsToMany
        if ($lensOptionIds !== null) {
            $ids = collect((array) $lensOptionIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->toArray();

            $record->compatibleLensOptions()->sync($ids);
        }

        return $record;
    }
}
