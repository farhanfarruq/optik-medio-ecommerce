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
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('lens_type')
                ->options([
                    'single_vision' => 'Single Vision',
                    'progressive' => 'Progressive',
                    'reading' => 'Reading',
                    'blue_light' => 'Blue Light',
                    'photochromic' => 'Photochromic',
                    'high_index' => 'High Index',
                    'anti_radiation' => 'Anti Radiation',
                ])
                ->native(false),
            Forms\Components\TextInput::make('right_sphere')->numeric()->label('OD SPH'),
            Forms\Components\TextInput::make('right_cylinder')->numeric()->label('OD CYL'),
            Forms\Components\TextInput::make('right_axis')->numeric()->minValue(0)->maxValue(180)->label('OD Axis'),
            Forms\Components\TextInput::make('right_add')->numeric()->label('OD ADD'),
            Forms\Components\TextInput::make('left_sphere')->numeric()->label('OS SPH'),
            Forms\Components\TextInput::make('left_cylinder')->numeric()->label('OS CYL'),
            Forms\Components\TextInput::make('left_axis')->numeric()->minValue(0)->maxValue(180)->label('OS Axis'),
            Forms\Components\TextInput::make('left_add')->numeric()->label('OS ADD'),
            Forms\Components\TextInput::make('pd_single')->numeric()->label('PD Single'),
            Forms\Components\TextInput::make('pd_right')->numeric()->label('PD Right'),
            Forms\Components\TextInput::make('pd_left')->numeric()->label('PD Left'),
            Forms\Components\FileUpload::make('attachment_path')
                ->label('Attachment')
                ->disk('public')
                ->directory('prescriptions')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(4096)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')
                ->maxLength(2000)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_default'),
            Forms\Components\Select::make('verified_by')
                ->relationship('verifier', 'name')
                ->searchable()
                ->preload()
                ->disabled(),
            Forms\Components\DateTimePicker::make('verified_at')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('lens_type')->badge()->placeholder('-'),
                Tables\Columns\TextColumn::make('right_sphere')->label('OD SPH')->placeholder('-'),
                Tables\Columns\TextColumn::make('left_sphere')->label('OS SPH')->placeholder('-'),
                Tables\Columns\TextColumn::make('pd_single')->label('PD')->placeholder('-'),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\IconColumn::make('verified_at')
                    ->label('Verified')
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
                        default    => 'Pending',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verified_at')
                    ->label('Verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('verified_at'),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),
                Tables\Filters\SelectFilter::make('verification_status')
                    ->options([
                        'pending'  => '⏳ Pending',
                        'approved' => '✅ Disetujui',
                        'rejected' => '❌ Ditolak',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('verify')
                    ->label('Approve')
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
}
