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
                \Filament\Schemas\Components\Section::make('Identitas Produk')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Produk')
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
                            ->required()
                            ->maxLength(80)
                            ->unique(Product::class, 'sku', ignoreRecord: true),
                        Forms\Components\TextInput::make('brand')
                            ->label('Brand')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('condition')
                            ->label('Kondisi')
                            ->options([
                                'new' => 'Baru',
                                'refurbished' => 'Refurbished',
                                'used' => 'Bekas',
                            ])
                            ->required()
                            ->default('new')
                            ->native(false),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Konten & Media')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Lengkap')
                            ->required()
                            ->minLength(80)
                            ->maxLength(65535)
                            ->rows(5)
                            ->helperText('Isi bahan, kegunaan, target pengguna, dan keunggulan produk.')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('images')
                            ->label('Gambar Produk')
                            ->required(fn (Get $get): bool => (bool) $get('is_active'))
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('products')
                            ->helperText('Upload minimal satu foto produk yang jelas.')
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Harga, Stok & Pengiriman')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Harga')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('stock')
                            ->label('Stok')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('Batas Stok Rendah')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(3),
                        Forms\Components\TextInput::make('weight')
                            ->label('Berat')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(250)
                            ->suffix('gram'),
                        Forms\Components\KeyValue::make('dimensions')
                            ->label('Dimensi Paket')
                            ->keyLabel('Sisi')
                            ->valueLabel('Ukuran cm')
                            ->helperText('Contoh key: panjang, lebar, tinggi.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Atribut Optik')
                    ->schema([
                        Forms\Components\Select::make('gender')
                            ->label('Target Pengguna')
                            ->options([
                                'men' => 'Pria',
                                'women' => 'Wanita',
                                'unisex' => 'Unisex',
                                'kids' => 'Anak',
                            ])
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('frame_shape')
                            ->label('Bentuk Frame')
                            ->options([
                                'aviator' => 'Aviator',
                                'browline' => 'Browline',
                                'cat_eye' => 'Cat Eye',
                                'geometric' => 'Geometric',
                                'oval' => 'Oval',
                                'rectangle' => 'Rectangle',
                                'round' => 'Round',
                                'square' => 'Square',
                                'wayfarer' => 'Wayfarer',
                            ])
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('frame_material')
                            ->label('Material Frame')
                            ->options([
                                'acetate' => 'Acetate',
                                'metal' => 'Metal',
                                'stainless_steel' => 'Stainless Steel',
                                'titanium' => 'Titanium',
                                'tr90' => 'TR90',
                                'ultem' => 'Ultem',
                            ])
                            ->searchable()
                            ->native(false),
                        Forms\Components\TextInput::make('frame_color')
                            ->label('Warna Frame')
                            ->maxLength(255),
                        Forms\Components\Select::make('face_size_fit')
                            ->label('Ukuran Wajah')
                            ->options([
                                'small' => 'Small',
                                'medium' => 'Medium',
                                'large' => 'Large',
                            ])
                            ->native(false),
                        Forms\Components\TextInput::make('lens_width')
                            ->label('Lebar Lensa')
                            ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                            ->numeric()
                            ->suffix('mm'),
                        Forms\Components\TextInput::make('bridge_width')
                            ->label('Bridge')
                            ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                            ->numeric()
                            ->suffix('mm'),
                        Forms\Components\TextInput::make('temple_length')
                            ->label('Panjang Temple')
                            ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                            ->numeric()
                            ->suffix('mm'),
                        Forms\Components\TextInput::make('frame_width')
                            ->label('Lebar Frame')
                            ->required(fn (Get $get): bool => self::requiresFrameDimensions($get))
                            ->numeric()
                            ->suffix('mm'),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Discovery, Garansi & Resep')
                    ->schema([
                        Forms\Components\TagsInput::make('tags')
                            ->label('Product Tags')
                            ->required()
                            ->placeholder('blue-light, daily, premium')
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('campaign_tags')
                            ->label('Campaign Landing Tags')
                            ->placeholder('office, premium, kids')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif Ditampilkan')
                            ->required()
                            ->default(true),
                        Forms\Components\Toggle::make('is_not_for_sale')
                            ->label('Tidak Dijual Online')
                            ->default(false),
                        Forms\Components\Toggle::make('is_best_seller')
                            ->label('Best Seller')
                            ->required()
                            ->default(false),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Product')
                            ->default(false),
                        Forms\Components\Toggle::make('is_new')
                            ->label('Produk Baru')
                            ->default(false),
                        Forms\Components\TextInput::make('recommendation_priority')
                            ->label('Recommendation Priority')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0)
                            ->helperText('Lebih tinggi berarti lebih diprioritaskan pada rekomendasi dan campaign.'),
                        Forms\Components\Toggle::make('is_prescription_required')
                            ->label('Butuh Resep')
                            ->required()
                            ->default(false),
                        Forms\Components\KeyValue::make('prescription_rules')
                            ->label('Prescription Rules')
                            ->keyLabel('Rule')
                            ->valueLabel('Value')
                            ->required(fn (Get $get): bool => (bool) $get('is_prescription_required'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                \Filament\Schemas\Components\Section::make('SEO & Metadata')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('google_product_category')
                            ->label('Google Product Category')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('gtin')
                            ->label('GTIN')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mpn')
                            ->label('MPN')
                            ->maxLength(255),
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
