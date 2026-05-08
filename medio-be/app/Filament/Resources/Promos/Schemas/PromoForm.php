<?php

namespace App\Filament\Resources\Promos\Schemas;

use Filament\Schemas\Schema;

class PromoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Tabs::make('Promo Details')
                    ->tabs([
                        \Filament\Schemas\Components\Tabs\Tab::make('General Information')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                \Filament\Forms\Components\Textarea::make('description')
                                    ->rows(3),
                                \Filament\Forms\Components\Select::make('type')
                                    ->options([
                                        'buy_x_get_y' => 'Buy X Get Y',
                                        'transaction_discount' => 'Transaction Discount',
                                        'product_discount' => 'Product Discount',
                                    ])
                                    ->required()
                                    ->reactive(),
                                \Filament\Schemas\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\DateTimePicker::make('start_date'),
                                        \Filament\Forms\Components\DateTimePicker::make('end_date'),
                                    ]),
                                \Filament\Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                                \Filament\Forms\Components\Toggle::make('is_banner_active')
                                    ->label('Tampilkan di Banner')
                                    ->default(false),
                                \Filament\Forms\Components\TextInput::make('usage_limit_per_user')
                                    ->label('Batas Pemakaian per User')
                                    ->numeric()
                                    ->helperText('Kosongkan jika tidak ada batas.'),
                            ]),
                        
                        \Filament\Schemas\Components\Tabs\Tab::make('Promo Rules')
                            ->schema([
                                // Buy X Get Y Section
                                \Filament\Schemas\Components\Section::make('Buy X Get Y Configuration')
                                    ->visible(fn ($get) => $get('type') === 'buy_x_get_y')
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('buyProducts')
                                            ->label('Pilih Produk')
                                            ->relationship('buyProducts', 'name')
                                            ->multiple()
                                            ->searchable(),
                                        \Filament\Forms\Components\Select::make('buy_brands')
                                            ->label('Pilih Merek')
                                            ->multiple()
                                            ->options(\App\Models\Product::whereNotNull('brand')->distinct()->pluck('brand', 'brand'))
                                            ->searchable(),
                                        \Filament\Forms\Components\TextInput::make('buy_quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required(fn ($get) => $get('type') === 'buy_x_get_y'),
                                        \Filament\Forms\Components\Select::make('get_product_id')
                                            ->label('Produk Gratis (Hadiah)')
                                            ->relationship('getProduct', 'name')
                                            ->searchable()
                                            ->required(fn ($get) => $get('type') === 'buy_x_get_y'),
                                        \Filament\Forms\Components\TextInput::make('get_quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required(fn ($get) => $get('type') === 'buy_x_get_y'),
                                    ])->columns(2),

                                // Transaction Discount Section
                                \Filament\Schemas\Components\Section::make('Transaction Discount Configuration')
                                    ->visible(fn ($get) => $get('type') === 'transaction_discount')
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('min_transaction_amount')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->required(fn ($get) => $get('type') === 'transaction_discount'),
                                        \Filament\Forms\Components\Select::make('discount_type')
                                            ->options([
                                                'percentage' => 'Percentage',
                                                'fixed' => 'Fixed Amount',
                                            ])
                                            ->required(fn ($get) => $get('type') === 'transaction_discount'),
                                        \Filament\Forms\Components\TextInput::make('discount_value')
                                            ->numeric()
                                            ->required(fn ($get) => $get('type') === 'transaction_discount'),
                                    ])->columns(2),

                                // Product Discount Section
                                \Filament\Schemas\Components\Section::make('Product Discount Configuration')
                                    ->visible(fn ($get) => $get('type') === 'product_discount')
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('discountProducts')
                                            ->label('Pilih Produk')
                                            ->relationship('discountProducts', 'name')
                                            ->multiple()
                                            ->searchable(),
                                        \Filament\Forms\Components\Select::make('discount_brands')
                                            ->label('Pilih Merek')
                                            ->multiple()
                                            ->options(\App\Models\Product::whereNotNull('brand')->distinct()->pluck('brand', 'brand'))
                                            ->searchable(),
                                        \Filament\Forms\Components\Select::make('discount_type')
                                            ->options([
                                                'percentage' => 'Percentage',
                                                'fixed' => 'Fixed Amount',
                                            ])
                                            ->required(fn ($get) => $get('type') === 'product_discount'),
                                        \Filament\Forms\Components\TextInput::make('discount_value')
                                            ->numeric()
                                            ->required(fn ($get) => $get('type') === 'product_discount'),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
