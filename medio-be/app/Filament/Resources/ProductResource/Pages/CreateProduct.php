<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Simpan lens option IDs sementara, hapus dari data sebelum create
        // (bukan kolom di tabel products)
        $this->lensOptionIds = $data['compatible_lens_option_ids'] ?? [];
        unset($data['compatible_lens_option_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->lensOptionIds)) {
            $ids = collect((array) $this->lensOptionIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->toArray();

            $this->record->compatibleLensOptions()->sync($ids);
        }
    }
}
