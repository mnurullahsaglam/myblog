<?php

namespace App\Filament\Clusters\Library\Resources;

use App\Filament\Clusters\Library;
use App\Filament\Clusters\Library\Resources\PublisherResource\Pages\CreatePublisher;
use App\Filament\Clusters\Library\Resources\PublisherResource\Pages\EditPublisher;
use App\Filament\Clusters\Library\Resources\PublisherResource\Pages\ListPublishers;
use App\Filament\Exports\PublisherExporter;
use App\Models\Publisher;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Placeholder;
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

class PublisherResource extends Resource
{
    protected static ?string $model = Publisher::class;

    protected static ?string $slug = 'publishers';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Yayınevi';

    protected static ?string $pluralLabel = 'Yayınevleri';

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
                    ->unique(Publisher::class, 'slug', fn($record) => $record),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?Publisher $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?Publisher $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
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
                    ->counts('books')
                    ->label('Kitap Sayısı')
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
                    ->exporter(PublisherExporter::class)
                    ->fileName(fn(Export $export): string => 'Yayınevi Listesi')
                    ->label('Dışa Aktar: Yayınevleri'),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublishers::route('/'),
            'create' => CreatePublisher::route('/create'),
            'edit' => EditPublisher::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
