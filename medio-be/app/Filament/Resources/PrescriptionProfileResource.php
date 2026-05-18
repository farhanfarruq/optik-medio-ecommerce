<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrescriptionProfileResource\Pages;
use App\Models\PrescriptionProfile;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PrescriptionProfileResource extends Resource
{
    protected static ?string $model = PrescriptionProfile::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Resep Optik';
    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Informasi Resep')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Pelanggan')
                        ->relationship('user', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('label')
                        ->label('Nama Resep')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('lens_type')
                        ->label('Fungsi Lensa Dasar')
                        ->helperText('Gunakan fungsi dasar lensa. Fitur seperti Blue Light, Photochromic, dan High Index dipilih saat konfigurasi produk.')
                        ->options(self::lensTypeOptions())
                        ->native(false),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Jadikan Resep Utama'),
                ])
                ->columns(2),
            \Filament\Schemas\Components\Section::make('Parameter OD / OS')
                ->schema([
                    Forms\Components\TextInput::make('right_sphere')->numeric()->step(0.25)->label('OD SPH'),
                    Forms\Components\TextInput::make('right_cylinder')->numeric()->step(0.25)->label('OD CYL'),
                    Forms\Components\TextInput::make('right_axis')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(180)
                        ->label('OD Axis')
                        ->disabled(fn (callable $get) => (float) ($get('right_cylinder') ?? 0) === 0.0),
                    Forms\Components\TextInput::make('right_add')
                        ->numeric()
                        ->step(0.25)
                        ->minValue(0)
                        ->maxValue(5)
                        ->label('OD ADD')
                        ->disabled(fn (callable $get) => $get('lens_type') !== 'progressive'),
                    Forms\Components\TextInput::make('left_sphere')->numeric()->step(0.25)->label('OS SPH'),
                    Forms\Components\TextInput::make('left_cylinder')->numeric()->step(0.25)->label('OS CYL'),
                    Forms\Components\TextInput::make('left_axis')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(180)
                        ->label('OS Axis')
                        ->disabled(fn (callable $get) => (float) ($get('left_cylinder') ?? 0) === 0.0),
                    Forms\Components\TextInput::make('left_add')
                        ->numeric()
                        ->step(0.25)
                        ->minValue(0)
                        ->maxValue(5)
                        ->label('OS ADD')
                        ->disabled(fn (callable $get) => $get('lens_type') !== 'progressive'),
                ])
                ->columns(4),
            \Filament\Schemas\Components\Section::make('Pupillary Distance (PD)')
                ->schema([
                    Forms\Components\Radio::make('pd_mode')
                        ->label('Mode PD')
                        ->options([
                            'single' => 'PD Tunggal',
                            'dual' => 'PD Ganda',
                        ])
                        ->default(fn (?PrescriptionProfile $record) => ($record?->pd_right !== null && $record?->pd_left !== null) ? 'dual' : 'single')
                        ->dehydrated(false)
                        ->live(),
                    Forms\Components\TextInput::make('pd_single')
                        ->numeric()
                        ->step(0.5)
                        ->minValue(50)
                        ->maxValue(75)
                        ->label('PD Tunggal')
                        ->helperText('Rentang umum 50 - 75 mm.')
                        ->disabled(fn (callable $get) => ($get('pd_mode') ?? 'single') !== 'single'),
                    Forms\Components\TextInput::make('pd_right')
                        ->numeric()
                        ->step(0.5)
                        ->minValue(25)
                        ->maxValue(38)
                        ->label('PD Kanan')
                        ->helperText('Rentang umum 25 - 38 mm.')
                        ->disabled(fn (callable $get) => ($get('pd_mode') ?? 'single') !== 'dual'),
                    Forms\Components\TextInput::make('pd_left')
                        ->numeric()
                        ->step(0.5)
                        ->minValue(25)
                        ->maxValue(38)
                        ->label('PD Kiri')
                        ->helperText('Rentang umum 25 - 38 mm.')
                        ->disabled(fn (callable $get) => ($get('pd_mode') ?? 'single') !== 'dual'),
                ])
                ->columns(2),
            \Filament\Schemas\Components\Section::make('Lampiran dan Catatan')
                ->schema([
                    Forms\Components\FileUpload::make('attachment_path')
                        ->label('Lampiran Resep')
                        ->disk('public')
                        ->directory('prescriptions')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(4096),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Pelanggan')
                        ->maxLength(2000),
                    Forms\Components\Select::make('verified_by')
                        ->label('Diverifikasi Oleh')
                        ->relationship('verifier', 'name')
                        ->searchable()
                        ->preload()
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('verified_at')
                        ->label('Tanggal Verifikasi')
                        ->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Nama Resep')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Pelanggan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('lens_type')
                    ->label('Fungsi Lensa')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::lensTypeLabel($state))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('right_sphere')->label('OD SPH')->placeholder('-'),
                Tables\Columns\TextColumn::make('left_sphere')->label('OS SPH')->placeholder('-'),
                Tables\Columns\TextColumn::make('pd_single')->label('PD Tunggal')->placeholder('-'),
                Tables\Columns\TextColumn::make('pd_right')->label('PD Kanan')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pd_left')->label('PD Kiri')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_default')->label('Utama')->boolean(),
                Tables\Columns\IconColumn::make('verified_at')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->getStateUsing(fn (PrescriptionProfile $record): bool => filled($record->verified_at)),
                Tables\Columns\TextColumn::make('verification_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default    => 'Menunggu',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verified_at')
                    ->label('Terverifikasi')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('verified_at'),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),
                Tables\Filters\SelectFilter::make('verification_status')
                    ->label('Status')
                    ->options([
                        'pending'  => '⏳ Menunggu',
                        'approved' => '✅ Disetujui',
                        'rejected' => '❌ Ditolak',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('verify')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PrescriptionProfile $record): bool => blank($record->verified_at))
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin (Opsional)')
                            ->rows(2)
                            ->placeholder('Catatan untuk pelanggan...'),
                    ])
                    ->action(fn (PrescriptionProfile $record, array $data) => $record->update([
                        'verified_by'         => auth()->id(),
                        'verified_at'         => now(),
                        'verification_status' => 'approved',
                        'admin_notes'         => $data['admin_notes'] ?? null,
                    ])),
                \Filament\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PrescriptionProfile $record): bool => blank($record->verified_at))
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Alasan Penolakan')
                            ->rows(3)
                            ->required()
                            ->placeholder('Jelaskan alasan penolakan resep...'),
                    ])
                    ->action(fn (PrescriptionProfile $record, array $data) => $record->update([
                        'verified_by'         => auth()->id(),
                        'verified_at'         => now(),
                        'verification_status' => 'rejected',
                        'admin_notes'         => $data['admin_notes'],
                    ])),
                \Filament\Actions\Action::make('unverify')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (PrescriptionProfile $record): bool => filled($record->verified_at))
                    ->requiresConfirmation()
                    ->action(fn (PrescriptionProfile $record) => $record->update([
                        'verified_by'         => null,
                        'verified_at'         => null,
                        'verification_status' => 'pending',
                        'admin_notes'         => null,
                    ])),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrescriptionProfiles::route('/'),
            'create' => Pages\CreatePrescriptionProfile::route('/create'),
            'edit' => Pages\EditPrescriptionProfile::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function lensTypeOptions(): array
    {
        return [
            'single_vision' => 'Single Vision',
            'progressive' => 'Progresif / Bifokal',
            'reading' => 'Single Vision Baca',
        ];
    }

    private static function lensTypeLabel(?string $state): string
    {
        return self::lensTypeOptions()[$state ?? ''] ?? ($state ?: '-');
    }
}
