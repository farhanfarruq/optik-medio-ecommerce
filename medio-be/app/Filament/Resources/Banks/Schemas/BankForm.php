<?php

namespace App\Filament\Resources\Banks\Schemas;

use Filament\Schemas\Schema;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Rekening Bank')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Nama Bank')
                            ->required()
                            ->placeholder('Contoh: Bank Central Asia (BCA)')
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('code')
                            ->label('Kode Bank')
                            ->placeholder('Contoh: 014')
                            ->maxLength(10),
                        \Filament\Forms\Components\TextInput::make('account_name')
                            ->label('Nama Pemilik Rekening')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('account_number')
                            ->label('Nomor Rekening')
                            ->required()
                            ->maxLength(50),
                        \Filament\Forms\Components\FileUpload::make('logo')
                            ->label('Logo Bank')
                            ->image()
                            ->disk('public')
                            ->directory('banks'),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
