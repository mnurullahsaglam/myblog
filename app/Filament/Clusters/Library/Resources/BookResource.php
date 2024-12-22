<?php

namespace App\Filament\Clusters\Library\Resources;

use App\Filament\Clusters\Library;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\CreateBook;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\EditBook;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\ListBooks;
use App\Filament\Clusters\Library\Resources\BookResource\Widgets\BooksOverview;
use App\Filament\Exports\BookExporter;
use App\Models\Book;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $slug = 'books';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $modelLabel = 'Kitap';

    protected static ?string $pluralLabel = 'Kitaplar';

    protected static ?string $cluster = Library::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('writer_id')
                    ->relationship('writer', 'name')
                    ->searchable()
                    ->required(),

                Select::make('publisher_id')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->required(),

                TextInput::make('name')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug($state))),

                TextInput::make('original_name'),

                TextInput::make('slug')
                    ->disabled()
                    ->required()
                    ->unique(Book::class, 'slug', fn($record) => $record),

                TextInput::make('page_count')
                    ->integer(),

                TextInput::make('publication_date')
                    ->integer(),

                TextInput::make('publication_location'),

                TextInput::make('edition_number')
                    ->integer(),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?Book $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?Book $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Kitap Adı')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('writer.name')
                    ->label('Yazar')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('publisher.name')
                    ->label('Yayınevi')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(BookExporter::class)
                    ->fileName(fn(Export $export): string => 'Kitap Listesi')
                    ->label('Dışa Aktar: Kitaplar'),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBooks::route('/'),
            'create' => CreateBook::route('/create'),
            'edit' => EditBook::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['writer', 'publisher']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'writer.name', 'publisher.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->writer) {
            $details['Writer'] = $record->writer->name;
        }

        if ($record->publisher) {
            $details['Publisher'] = $record->publisher->name;
        }

        return $details;
    }

    public static function getWidgets(): array
    {
        return [
            BooksOverview::class,
        ];
    }
}
