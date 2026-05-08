<?php

namespace App\Filament\Resources;

use App\Enums\UserAffiliatorStatus;
use App\Filament\Resources\UserAffiliatorResource\Pages\CreateUserAffiliator;
use App\Filament\Resources\UserAffiliatorResource\Pages\EditUserAffiliator;
use App\Filament\Resources\UserAffiliatorResource\Pages\ListUserAffiliators;
use App\Models\UserAffiliator;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserAffiliatorResource extends Resource
{
    protected static ?string $model = UserAffiliator::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'Affiliate & Komisi';
    protected static ?string $navigationLabel = 'Affiliator';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Profil Affiliator')
                ->schema([
                    Forms\Components\Select::make('user_id')->relationship('user', 'name')->required()->searchable()->preload(),
                    Forms\Components\TextInput::make('affiliate_code')->required()->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'suspended' => 'Suspended',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('commission_rate_percentage')->numeric()->required()->default(0),
                    Forms\Components\Select::make('approved_by')->relationship('approvedBy', 'name')->searchable()->preload(),
                    Forms\Components\DateTimePicker::make('approved_at'),
                    Forms\Components\DateTimePicker::make('rejected_at'),
                    Forms\Components\Textarea::make('rejection_reason'),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('affiliate_code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserAffiliatorStatus | string | null $state): string => match (self::resolveStatusValue($state)) {
                        UserAffiliatorStatus::Approved->value => 'Approved',
                        UserAffiliatorStatus::Pending->value => 'Pending',
                        UserAffiliatorStatus::Rejected->value => 'Rejected',
                        UserAffiliatorStatus::Suspended->value => 'Suspended',
                        default => '-',
                    })
                    ->color(fn (UserAffiliatorStatus | string | null $state) => match (self::resolveStatusValue($state)) {
                        UserAffiliatorStatus::Approved->value => 'success',
                        UserAffiliatorStatus::Pending->value => 'warning',
                        UserAffiliatorStatus::Rejected->value => 'danger',
                        UserAffiliatorStatus::Suspended->value => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('commission_rate_percentage')->suffix('%'),
                Tables\Columns\TextColumn::make('approved_at')->dateTime('d M Y, H:i')->placeholder('-'),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (UserAffiliator $record) => $record->status !== UserAffiliatorStatus::Approved)
                    ->requiresConfirmation()
                    ->action(fn (UserAffiliator $record) => $record->update([
                        'status' => UserAffiliatorStatus::Approved,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ])),
                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (UserAffiliator $record) => $record->status !== UserAffiliatorStatus::Rejected)
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')->required(),
                    ])
                    ->action(fn (UserAffiliator $record, array $data) => $record->update([
                        'status' => UserAffiliatorStatus::Rejected,
                        'approved_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $data['rejection_reason'],
                    ])),
                \Filament\Actions\EditAction::make(),
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
            'index' => ListUserAffiliators::route('/'),
            'create' => CreateUserAffiliator::route('/create'),
            'edit' => EditUserAffiliator::route('/{record}/edit'),
        ];
    }

    private static function resolveStatusValue(UserAffiliatorStatus | string | null $status): ?string
    {
        if ($status instanceof UserAffiliatorStatus) {
            return $status->value;
        }

        return $status;
    }
}
