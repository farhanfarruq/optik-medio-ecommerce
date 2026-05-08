<?php

namespace App\Filament\Resources\Banks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo'),
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Nama Bank')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('account_name')
                    ->label('Atas Nama')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('account_number')
                    ->label('Nomor Rekening')
                    ->searchable(),
                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
