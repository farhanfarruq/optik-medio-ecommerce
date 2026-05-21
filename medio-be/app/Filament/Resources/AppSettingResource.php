<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSettingResource\Pages;
use App\Models\AppSetting;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static string | \UnitEnum | null $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Pengaturan App';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Konfigurasi Sistem')
                    ->description('Atur parameter e-commerce di sini.')
                    ->schema([
                        Forms\Components\Select::make('group')
                            ->label('Kategori Pengaturan')
                            ->options([
                                'General' => 'Umum',
                                'SEO' => 'SEO & Metadata',
                                'Payment' => 'Pembayaran',
                                'Shipping' => 'Pengiriman',
                                'Social Media' => 'Media Sosial',
                                'Integrations' => 'Integrasi',
                            ])
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('group')
                                    ->required()
                            ]),
                        Forms\Components\TextInput::make('key')
                            ->label('Kunci (Key)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Contoh: store_name'),
                        Forms\Components\Select::make('type')
                            ->label('Tipe Data')
                            ->options([
                                'string' => 'Teks Biasa',
                                'text' => 'Teks Panjang',
                                'boolean' => 'Boolean (On/Off)',
                                'json' => 'JSON',
                                'number' => 'Angka',
                            ])
                            ->default('string')
                            ->reactive(),
                        Forms\Components\TextInput::make('value')
                            ->label('Nilai (Value)')
                            ->visible(fn ($get) => in_array($get('type'), ['string', 'number']))
                            ->required(),
                        Forms\Components\Textarea::make('value')
                            ->label('Nilai (Value)')
                            ->visible(fn ($get) => in_array($get('type'), ['text', 'json']))
                            ->rows(5)
                            ->required(),
                        Forms\Components\Toggle::make('value')
                            ->label('Aktif / Matikan')
                            ->visible(fn ($get) => $get('type') === 'boolean')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->searchable(),
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\TextColumn::make('value')->limit(50),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSettings::route('/'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }
}
