<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-newspaper';
    protected static string | \UnitEnum | null $navigationGroup = 'Konten';
    protected static ?string $navigationLabel = 'Blog';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // ── Left Column ─────────────────────────────────────
            \Filament\Schemas\Components\Section::make('Konten Artikel')
                ->icon('heroicon-o-document-text')
                ->columnSpan(2)
                ->components([
                    Forms\Components\TextInput::make('title')
                        ->label('Judul Artikel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug($state));
                                $set('meta_title', $state);
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('URL Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(Article::class, 'slug', ignoreRecord: true)
                        ->helperText('URL: /blog/{slug} — otomatis dari judul'),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('Ringkasan / Excerpt')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Ringkasan singkat artikel (tampil di listing blog)'),
                    Forms\Components\RichEditor::make('content')
                        ->label('Konten')
                        ->required()
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'attachFiles',
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ]),
                ]),

            // ── Right Column ─────────────────────────────────────
            \Filament\Schemas\Components\Section::make('Penerbitan')
                ->icon('heroicon-o-paper-airplane')
                ->columnSpan(1)
                ->components([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Publish Artikel')
                        ->helperText('Aktifkan untuk menampilkan ke publik')
                        ->default(false)
                        ->live(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Jadwal Tayang')
                        ->helperText('Kosongkan untuk publish sekarang')
                        ->nullable(),
                    Forms\Components\Select::make('author_id')
                        ->label('Penulis')
                        ->options(User::where('role', 'admin')->pluck('name', 'id'))
                        ->default(fn () => auth()->id())
                        ->required(),
                    Forms\Components\TagsInput::make('tags')
                        ->label('Tags')
                        ->helperText('Tekan Enter atau koma untuk tambah tag')
                        ->placeholder('contoh: kacamata, lensa, optik'),
                ]),

            \Filament\Schemas\Components\Section::make('Gambar Utama')
                ->icon('heroicon-o-photo')
                ->columnSpan(1)
                ->components([
                    Forms\Components\FileUpload::make('featured_image')
                        ->label('Gambar Featured')
                        ->image()
                        ->disk('public')
                        ->directory('articles')
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeTargetWidth('1200')
                        ->imageResizeTargetHeight('630')
                        ->helperText('Ukuran ideal: 1200×630px (rasio 16:9)'),
                ]),

            \Filament\Schemas\Components\Section::make('SEO Metadata')
                ->icon('heroicon-o-magnifying-glass')
                ->description('Optimalkan artikel untuk mesin pencari Google')
                ->columnSpan(3)
                ->components([
                    Forms\Components\TextInput::make('meta_title')
                        ->label('Meta Title (SEO)')
                        ->maxLength(70)
                        ->helperText('Ideal: 50-60 karakter. Tampil di hasil pencarian Google.')
                        ->suffix(fn ($state) => strlen($state ?? '') . '/70'),
                    Forms\Components\Textarea::make('meta_description')
                        ->label('Meta Description (SEO)')
                        ->maxLength(300)
                        ->rows(3)
                        ->helperText('Ideal: 150-160 karakter. Deskripsi yang tampil di Google.'),
                ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('')
                    ->disk('public')
                    ->height(50)
                    ->width(80),
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Penulis')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tags')
                    ->label('Tags')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Tayang')
                    ->dateTime('d M Y')
                    ->placeholder('Draft'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status')
                    ->trueLabel('Published')
                    ->falseLabel('Draft')
                    ->placeholder('Semua'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Preview Artikel'),
                \Filament\Actions\Action::make('toggle_publish')
                    ->label(fn (Article $record): string => $record->is_published ? 'Unpublish' : 'Publish')
                    ->icon(fn (Article $record): string => $record->is_published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Article $record): string => $record->is_published ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Article $record) => $record->update([
                        'is_published' => !$record->is_published,
                        'published_at' => !$record->is_published ? now() : $record->published_at,
                    ])),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('publish_all')
                        ->label('Publish Semua')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_published' => true, 'published_at' => now()])),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make()
                    ->columnSpanFull()
                    ->columns(1)
                    ->extraAttributes(['class' => 'text-center'])
                    ->components([
                        \Filament\Infolists\Components\ImageEntry::make('featured_image')
                            ->hiddenLabel()
                            ->disk('public')
                            ->width('100%')
                            ->height('auto')
                            ->extraImgAttributes(['class' => 'w-full h-auto rounded-xl mx-auto shadow-sm']),
                        
                        \Filament\Infolists\Components\TextEntry::make('title')
                            ->hiddenLabel()
                            ->size('lg')
                            ->weight('bold')
                            ->extraAttributes(['class' => 'text-2xl mt-4']),
                            
                        \Filament\Schemas\Components\Group::make([
                            \Filament\Infolists\Components\TextEntry::make('author.name')
                                ->hiddenLabel()
                                ->icon('heroicon-o-user')
                                ->formatStateUsing(fn ($state) => 'Oleh: ' . ($state ?? 'Admin')),
                                
                            \Filament\Infolists\Components\TextEntry::make('published_at')
                                ->hiddenLabel()
                                ->icon('heroicon-o-calendar')
                                ->dateTime('d M Y')
                                ->placeholder('Draft'),
                        ])->extraAttributes(['class' => 'flex justify-center gap-4 text-gray-500']),
                        
                        \Filament\Infolists\Components\TextEntry::make('content')
                            ->hiddenLabel()
                            ->html()
                            ->extraAttributes(['class' => 'prose max-w-none dark:prose-invert mx-auto text-left mt-6 border-t pt-6']),
                    ])
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit'   => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
