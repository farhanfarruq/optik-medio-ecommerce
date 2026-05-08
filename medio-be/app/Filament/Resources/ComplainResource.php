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

class ComplainResource extends Resource
{
    protected static ?string $model = Complain::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string | \UnitEnum | null $navigationGroup = 'Content Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Tiket Komplain')
                ->schema([
                    Forms\Components\Select::make('user_id')->relationship('user', 'name')->disabled(),
                    Forms\Components\Select::make('order_id')->relationship('order', 'order_number')->disabled(),
                    Forms\Components\TextInput::make('subject')->disabled(),
                    Forms\Components\TextInput::make('contact_phone'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                    Forms\Components\Select::make('handled_by')->relationship('handledBy', 'name')->searchable()->preload(),
                    Forms\Components\DateTimePicker::make('resolved_at'),
                    Forms\Components\Textarea::make('message')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('admin_notes')->columnSpanFull(),
                    Forms\Components\TextInput::make('attachment_path')->disabled()->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('user.name')->label('Pelanggan')->searchable(),
                Tables\Columns\TextColumn::make('order.order_number')->label('Order')->placeholder('-'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('handledBy.name')->label('Handled By')->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y, H:i'),
            ])
            ->actions([
                \Filament\Actions\Action::make('resolve')
                    ->label('Resolve')
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
