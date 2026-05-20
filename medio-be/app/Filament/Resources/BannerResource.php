<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Filament\Support\PublicUpload;
use App\Models\Banner;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static string | \UnitEnum | null $navigationGroup = 'Konten';
    protected static ?string $navigationLabel = 'Banner';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Banner Dinamis')
                ->schema([
                    Forms\Components\Placeholder::make('banner_preview')
                        ->label('Preview (Tampilan di Website)')
                        ->content(function ($get) {
                            $title = $get('title') ?: 'Judul Banner';
                            $subtitle = $get('subtitle') ?: 'Sub judul akan muncul di sini';
                            $cta = $get('cta_label') ?: 'TOMBOL AKSI';
                            
                            return new \Illuminate\Support\HtmlString('
                                <div style="position: relative; width: 100%; aspect-ratio: 21/9; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); overflow: hidden; border-radius: 8px;">
                                    <div style="position: absolute; inset: 0; padding: 2rem; display: flex; flex-direction: column; justify-content: center; z-index: 10;">
                                        <p style="color: #c19a51; font-size: 0.65rem; font-weight: 900; letter-spacing: 0.3em; text-transform: uppercase; margin-bottom: 0.5rem;">Penawaran Spesial</p>
                                        <h3 style="color: white; font-size: 1.5rem; font-weight: 900; margin: 0 0 0.5rem 0;">' . e($title) . '</h3>
                                        <p style="color: #d6d3d1; font-size: 0.875rem; margin-bottom: 1rem;">' . e($subtitle) . '</p>
                                        <div>
                                            <span style="display: inline-block; padding: 0.5rem 1.5rem; border: 1px solid rgba(255,255,255,0.3); color: white; font-size: 0.75rem; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">' . e($cta) . '</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('title')->live(onBlur: true),
                    Forms\Components\TextInput::make('subtitle')->live(onBlur: true),
                    PublicUpload::image(Forms\Components\FileUpload::make('image_path'), 'banners', '180px')->required(),
                    Forms\Components\TextInput::make('cta_label')->live(onBlur: true),
                    Forms\Components\Select::make('link_type')
                        ->options([
                            'none' => 'Tanpa Link',
                            'product' => 'Produk',
                            'category' => 'Kategori',
                            'external' => 'External URL',
                        ])
                        ->default('none')
                        ->live(),
                    Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->visible(fn ($get) => $get('link_type') === 'product'),
                    Forms\Components\Select::make('category_id')->relationship('category', 'name')->searchable()->preload()->visible(fn ($get) => $get('link_type') === 'category'),
                    Forms\Components\TextInput::make('external_url')->url()->visible(fn ($get) => $get('link_type') === 'external'),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\DateTimePicker::make('starts_at'),
                    Forms\Components\DateTimePicker::make('ends_at'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Banner'),
                Tables\Columns\TextColumn::make('title')->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('link_type')->badge(),
                Tables\Columns\TextColumn::make('product.name')->label('Produk')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('category.name')->label('Kategori')->placeholder('-')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
