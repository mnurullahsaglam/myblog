<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Library\Resources;

use App\Filament\Clusters\Library;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\CreateBook;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\EditBook;
use App\Filament\Clusters\Library\Resources\BookResource\Pages\ListBooks;
use App\Filament\Clusters\Library\Resources\BookResource\Widgets\BooksOverview;
use App\Filament\Exports\BookExporter;
use App\Models\Book;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $slug = 'books';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $cluster = Library::class;

    public static function getModelLabel(): string
    {
        return __('Book');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Books');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('writer_id')
                    ->relationship('writer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('İsim')
                            ->required()
                            ->autofocus()
                            ->autocapitalize(),
                    ]),

                Select::make('publisher_id')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('İsim')
                            ->required()
                            ->autofocus()
                            ->autocapitalize(),
                    ]),

                TextInput::make('name')
                    ->required()
                    ->string()
                    ->minLength(3)
                    ->maxLength(255)
                    ->autocapitalize()
                    ->autofocus(),

                TextInput::make('original_name')
                    ->string()
                    ->minLength(3)
                    ->maxLength(255)
                    ->autocapitalize(),

                TextInput::make('page_count')
                    ->integer(),

                TextInput::make('publication_date')
                    ->integer(),

                TextInput::make('publication_location'),

                TextInput::make('edition_number')
                    ->integer(),

                FileUpload::make('image')
                    ->image()
                    ->directory('books')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('writer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('publisher.name')
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
                    ->exporter(BookExporter::class),
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

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if (! $record instanceof Book) {
            return $details;
        }

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
