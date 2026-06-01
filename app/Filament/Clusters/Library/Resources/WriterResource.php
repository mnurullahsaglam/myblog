<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Library\Resources;

use App\Filament\Clusters\Library;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\CreateWriter;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\EditWriter;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\ListWriters;
use App\Filament\Exports\WriterExporter;
use App\Models\Writer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WriterResource extends Resource
{
    protected static ?string $model = Writer::class;

    protected static ?string $slug = 'writers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static ?string $cluster = Library::class;

    public static function getModelLabel(): string
    {
        return __('Writer');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Writers');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->autofocus()
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->image()
                    ->directory('writers')
                    ->columnSpanFull(),

                RichEditor::make('bio')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                TextInput::make('birth_year')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(date('Y')),

                TextInput::make('death_year')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(date('Y')),

                TextInput::make('birth_place')
                    ->maxLength(255),

                TextInput::make('death_place')
                    ->maxLength(255),
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

                TextColumn::make('books_count')
                    ->counts('books')
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
                    ->exporter(WriterExporter::class),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWriters::route('/'),
            'create' => CreateWriter::route('/create'),
            'edit' => EditWriter::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
