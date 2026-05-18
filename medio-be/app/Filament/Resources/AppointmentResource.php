<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static string | \UnitEnum | null   $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Appointment';
    protected static ?int    $navigationSort  = 6;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('status')
                ->options([
                    'pending'   => 'Pending',
                    'confirmed' => 'Dikonfirmasi',
                    'completed' => 'Selesai',
                    'cancelled' => 'Dibatalkan',
                    'no_show'   => 'Tidak Hadir',
                ])
                ->required(),
            Forms\Components\Textarea::make('admin_notes')->rows(3)->label('Catatan Admin')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('appointment_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('appointment_number')->label('No.')->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Pelanggan')->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')->label('Telepon')->searchable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Cabang')->badge()->color('info')->searchable(),
                Tables\Columns\TextColumn::make('appointment_date')->label('Tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('appointment_time')->label('Waktu'),
                Tables\Columns\TextColumn::make('service_type')->label('Layanan')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'eye_test'         => 'Tes Mata',
                        'pickup'           => 'Ambil Pesanan',
                        'fitting'          => 'Fitting',
                        'consultation'     => 'Konsultasi',
                        'lens_replacement' => 'Ganti Lensa',
                        default            => $state,
                    })
                    ->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show'   => 'gray',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'confirmed' => 'Dikonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan']),
                Tables\Filters\SelectFilter::make('branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn ($q) => $q->whereDate('appointment_date', today())),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Mendatang')
                    ->query(fn ($q) => $q->where('appointment_date', '>=', today())->whereIn('status', ['pending', 'confirmed'])),
            ])
            ->actions([
                \Filament\Actions\Action::make('confirm')
                    ->label('Konfirmasi')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Appointment $r) => $r->status === 'pending')
                    ->action(fn (Appointment $r) => $r->update(['status' => 'confirmed', 'confirmed_at' => now()])),
                \Filament\Actions\Action::make('complete')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(fn (Appointment $r) => $r->status === 'confirmed')
                    ->action(fn (Appointment $r) => $r->update(['status' => 'completed', 'completed_at' => now()])),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('confirm_all')
                        ->label('Konfirmasi Semua')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'confirmed', 'confirmed_at' => now()])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'edit'  => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
