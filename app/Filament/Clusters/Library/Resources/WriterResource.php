<?php

namespace App\Filament\Clusters\Library\Resources;

use App\Filament\Clusters\Library;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\CreateWriter;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\EditWriter;
use App\Filament\Clusters\Library\Resources\WriterResource\Pages\ListWriters;
use App\Filament\Exports\WriterExporter;
use App\Models\Writer;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class WriterResource extends Resource
{
    protected static ?string $model = Writer::class;

    protected static ?string $slug = 'writers';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $modelLabel = 'Yazar';

    protected static ?string $pluralLabel = 'Yazarlar';

    protected static ?string $cluster = Library::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->disabled()
                    ->required()
                    ->unique(Writer::class, 'slug', fn($record) => $record),

                FileUpload::make('image')
                    ->label('Fotoğraf')
                    ->image()
                    ->directory('writers')
                    ->columnSpanFull(),

                RichEditor::make('bio')
                    ->label('Biyografi')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                DatePicker::make('birth_date')
                    ->label('Doğum Tarihi'),

                DatePicker::make('death_date')
                    ->label('Ölüm Tarihi'),

                TextInput::make('birth_place')
                    ->label('Doğum Yeri')
                    ->maxLength(255),

                TextInput::make('death_place')
                    ->label('Ölüm Yeri')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('İsim')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('books_count')
                    ->label('Kitap Sayısı')
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
                    ->exporter(WriterExporter::class)
                    ->fileName(fn(Export $export): string => 'Yazar Listesi')
                    ->label('Dışa Aktar: Yazarlar'),
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
