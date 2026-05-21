<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplainResource\Pages\EditComplain;
use App\Filament\Resources\ComplainResource\Pages\ListComplains;
use App\Models\Complain;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ComplainResource extends Resource
{
    protected static ?string $model = Complain::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string | \UnitEnum | null $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Komplain';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', 'open')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Grid::make(2)->schema([

                \Filament\Schemas\Components\Section::make('Detail Komplain')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('complaint_type')
                            ->label('Jenis')
                            ->options([
                                'general' => 'Komplain Umum',
                                'shipping_protection' => 'Klaim Proteksi Pengiriman',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')->disabled()->label('Pelanggan'),
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'order_number')->disabled()->label('Pesanan'),
                        Forms\Components\TextInput::make('subject')
                            ->disabled()->label('Subjek')->columnSpanFull(),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Kontak'),
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Diselesaikan Pada'),
                        Forms\Components\Textarea::make('message')
                            ->disabled()->label('Pesan Customer')->rows(5)->columnSpanFull(),

                        // Lampiran: preview gambar/video/PDF langsung di admin
                        Forms\Components\Placeholder::make('attachment_preview')
                            ->label('Lampiran Bukti')
                            ->columnSpanFull()
                            ->content(function ($record): \Illuminate\Support\HtmlString {
                                if (! $record || ! $record->attachment_path) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<span class="text-sm text-gray-400 italic">Tidak ada lampiran.</span>'
                                    );
                                }

                                $path = $record->attachment_path;
                                $url  = Storage::disk('public')->url($path);
                                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                                $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                                $videoExts = ['mp4', 'mov', 'webm'];

                                if (in_array($ext, $imageExts)) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="space-y-2">'
                                        . '<img src="' . e($url) . '" alt="Lampiran" style="max-width:480px;max-height:360px;border-radius:6px;border:1px solid #e5e7eb;object-fit:contain;" />'
                                        . '<div><a href="' . e($url) . '" target="_blank" class="text-xs text-primary-600 underline">Buka di tab baru ↗</a></div>'
                                        . '</div>'
                                    );
                                }

                                if (in_array($ext, $videoExts)) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="space-y-2">'
                                        . '<video controls style="max-width:480px;max-height:320px;border-radius:6px;border:1px solid #e5e7eb;">'
                                        . '<source src="' . e($url) . '" type="video/' . e($ext) . '">'
                                        . 'Browser Anda tidak mendukung pemutaran video.'
                                        . '</video>'
                                        . '<div><a href="' . e($url) . '" target="_blank" class="text-xs text-primary-600 underline">Unduh video ↗</a></div>'
                                        . '</div>'
                                    );
                                }

                                // PDF atau file lain
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . e($url) . '" target="_blank" '
                                    . 'class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 rounded border border-gray-300 transition">'
                                    . '📎 Lihat Lampiran (' . strtoupper(e($ext)) . ')'
                                    . '</a>'
                                );
                            }),
                    ]),

                \Filament\Schemas\Components\Section::make('Tindakan Admin')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'open'        => 'Terbuka',
                                'in_progress' => 'Diproses',
                                'resolved'    => 'Selesai',
                                'rejected'    => 'Ditolak',
                            ])
                            ->required()->label('Status'),
                        Forms\Components\Select::make('handled_by')
                            ->relationship('handledBy', 'name')
                            ->searchable()->preload()->label('Ditangani Oleh'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan / Respons Admin')
                            ->rows(8)->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('complaint_type_label')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (Complain $record) => $record->complaint_type === 'shipping_protection' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('subject')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('user.name')->label('Pelanggan')->searchable(),
                Tables\Columns\TextColumn::make('order.order_number')->label('Pesanan')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('handledBy.name')->label('Ditangani Oleh')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y, H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('complaint_type')
                    ->label('Jenis')
                    ->options([
                        'general' => 'Komplain Umum',
                        'shipping_protection' => 'Klaim Proteksi Pengiriman',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('resolve')
                    ->label('Selesaikan')
                    ->color('success')
                    ->visible(fn (Complain $record) => $record->status !== 'resolved')
                    ->action(fn (Complain $record) => $record->update([
                        'status' => 'resolved',
                        'handled_by' => auth()->id(),
                        'resolved_at' => now(),
                    ])),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplains::route('/'),
            'edit' => EditComplain::route('/{record}/edit'),
        ];
    }
}
