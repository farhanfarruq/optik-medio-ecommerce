<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Produk';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state) . '-' . Str::random(5)) : null),
                Forms\Components\TextInput::make('slug')
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->maxLength(255)
                    ->unique(Product::class, 'slug', ignoreRecord: true),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->unique(Product::class, 'sku', ignoreRecord: true),
                Forms\Components\TextInput::make('brand')
                    ->maxLength(255),
                Forms\Components\Select::make('gender')
                    ->options([
                        'men' => 'Men',
                        'women' => 'Women',
                        'unisex' => 'Unisex',
                        'kids' => 'Kids',
                    ])
                    ->searchable()
                    ->native(false),
                Forms\Components\TextInput::make('frame_shape')
                    ->label('Frame Shape')
                    ->maxLength(255),
                Forms\Components\TextInput::make('frame_material')
                    ->label('Frame Material')
                    ->maxLength(255),
                Forms\Components\TextInput::make('frame_color')
                    ->label('Frame Color')
                    ->maxLength(255),
                Forms\Components\Select::make('face_size_fit')
                    ->label('Face Size Fit')
                    ->options([
                        'small' => 'Small',
                        'medium' => 'Medium',
                        'large' => 'Large',
                    ])
                    ->native(false),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('weight')
                    ->required()
                    ->numeric()
                    ->default(1000)
                    ->suffix('gram'),
                Forms\Components\TextInput::make('lens_width')
                    ->label('Lens Width')
                    ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                    ->numeric()
                    ->suffix('mm'),
                Forms\Components\TextInput::make('bridge_width')
                    ->label('Bridge Width')
                    ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                    ->numeric()
                    ->suffix('mm'),
                Forms\Components\TextInput::make('temple_length')
                    ->label('Temple Length')
                    ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                    ->numeric()
                    ->suffix('mm'),
                Forms\Components\TextInput::make('frame_width')
                    ->label('Frame Width')
                    ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                    ->numeric()
                    ->suffix('mm'),
                Forms\Components\TextInput::make('google_product_category')
                    ->label('Google Product Category')
                    ->maxLength(255),
                Forms\Components\TagsInput::make('tags')
                    ->label('Product Tags')
                    ->placeholder('blue-light, daily, premium')
                    ->columnSpanFull(),
                Forms\Components\TagsInput::make('campaign_tags')
                    ->label('Campaign Landing Tags')
                    ->placeholder('lebaran, back-to-school, office')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('gtin')
                    ->label('GTIN')
                    ->maxLength(255),
                Forms\Components\TextInput::make('mpn')
                    ->label('MPN')
                    ->maxLength(255),
                Forms\Components\Select::make('condition')
                    ->options([
                        'new' => 'New',
                        'refurbished' => 'Refurbished',
                        'used' => 'Used',
                    ])
                    ->default('new')
                    ->native(false),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Forms\Components\Toggle::make('is_best_seller')
                    ->required()
                    ->default(false),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured Product')
                    ->default(false),
                Forms\Components\TextInput::make('recommendation_priority')
                    ->label('Recommendation Priority')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0)
                    ->helperText('Lebih tinggi berarti lebih diprioritaskan pada rekomendasi dan campaign.'),
                Forms\Components\Toggle::make('is_prescription_required')
                    ->required()
                    ->default(false),
                Forms\Components\KeyValue::make('prescription_rules')
                    ->label('Prescription Rules')
                    ->keyLabel('Rule')
                    ->valueLabel('Value')
                    ->required(fn (Get $get): bool => (bool) $get('is_prescription_required'))
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('images')
                    ->required(fn (Get $get): bool => (bool) $get('is_active'))
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('products')
                    ->columnSpanFull(),

                \Filament\Schemas\Components\Section::make('SEO & Metadata')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(70)
                            ->helperText('Maks 70 karakter. Kosongkan untuk pakai nama produk.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->maxLength(160)
                            ->rows(2)
                            ->helperText('Maks 160 karakter. Kosongkan untuk pakai deskripsi produk.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('canonical_slug')
                            ->label('Canonical Slug')
                            ->helperText('Kosongkan untuk pakai slug produk.')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('og_image')
                            ->label('OG Image URL')
                            ->url()
                            ->helperText('URL gambar untuk Open Graph / social share. Kosongkan untuk pakai gambar utama produk.')
                            ->maxLength(500),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->disk('public')
                    ->stacked()
                    ->circular(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('sku')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('brand')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('frame_shape')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('face_size_fit')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('stock')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('recommendation_priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_best_seller')->label('Best Seller')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('Featured')->boolean(),
                Tables\Columns\IconColumn::make('is_prescription_required')->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_best_seller')->label('Best Seller'),
            ])
            ->actions([ \Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make() ])
            ->bulkActions([ \Filament\Actions\BulkActionGroup::make([ \Filament\Actions\DeleteBulkAction::make() ]) ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    private static function requiresFrameDimensions(Get $get): bool
    {
        return filled($get('frame_shape'))
            || filled($get('frame_material'))
            || filled($get('frame_color'))
            || filled($get('face_size_fit'));
    }
}
