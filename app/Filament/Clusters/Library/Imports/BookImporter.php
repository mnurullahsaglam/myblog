<?php

namespace App\Filament\Clusters\Library\Imports;

use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class BookImporter extends Importer
{
    protected static ?string $model = Book::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('writer')
                ->label('Yazar')
                ->requiredMapping()
                ->relationship(resolveUsing: function (string $state): ?Writer {
                    return Writer::firstOrCreate([
                        'name' => $state,
                    ]);
                })
                ->rules(['required', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Yazar'),

            ImportColumn::make('publisher')
                ->label('Yayınevi')
                ->requiredMapping()
                ->relationship(resolveUsing: function (string $state): ?Publisher {
                    return Publisher::firstOrCreate([
                        'name' => $state,
                    ]);
                })
                ->rules(['required', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Yayınevi'),

            ImportColumn::make('name')
                ->label('Kitap Adı')
                ->requiredMapping()
                ->rules(['required', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Kitap Adı'),

            ImportColumn::make('original_name')
                ->label('Orijinal Adı')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Orijinal Adı'),

            ImportColumn::make('page_count')
                ->label('Sayfa Sayısı')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0'])
                ->exampleHeader('Sayfa Sayısı'),

            ImportColumn::make('publication_date')
                ->label('Yayın Tarihi')
                ->numeric()
                ->rules(['nullable', 'date_format:Y'])
                ->exampleHeader('Yayın Tarihi'),

            ImportColumn::make('publication_location')
                ->label('Basım Yeri')
                ->rules(['nullable', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Basım Yeri'),

            ImportColumn::make('edition_number')
                ->label('Baskı Sayısı')
                ->rules(['nullable', 'numeric', 'min:1', 'max:1000'])
                ->exampleHeader('Baskı Sayısı'),
        ];
    }

    public function resolveRecord(): ?Book
    {
        return Book::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'İçeri aktarım tamamladndı. ' . number_format($import->successful_rows) . ' kitap eklendi.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' kitap içeri aktarılamadı.';
        }

        return $body;
    }
}
