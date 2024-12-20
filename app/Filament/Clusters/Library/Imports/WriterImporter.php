<?php

namespace App\Filament\Clusters\Library\Imports;

use App\Models\Writer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class WriterImporter extends Importer
{
    protected static ?string $model = Writer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('İsim')
                ->requiredMapping()
                ->rules(['required', 'string', 'min:3', 'max:255'])
                ->exampleHeader('İsim'),

            ImportColumn::make('bio')
                ->label('Biyografi')
                ->rules(['nullable', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Biyografi'),

            ImportColumn::make('birth_date')
                ->label('Doğum Tarihi')
                ->rules(['nullable', 'date'])
                ->exampleHeader('Doğum Tarihi'),

            ImportColumn::make('death_date')
                ->label('Ölüm Tarihi')
                ->rules(['nullable', 'date'])
                ->exampleHeader('Ölüm Tarihi'),

            ImportColumn::make('birth_place')
                ->label('Doğum Yeri')
                ->rules(['nullable', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Doğum Yeri'),

            ImportColumn::make('death_place')
                ->label('Ölüm Yeri')
                ->rules(['nullable', 'string', 'min:3', 'max:255'])
                ->exampleHeader('Ölüm Yeri'),
        ];
    }

    public function resolveRecord(): ?Writer
    {
        return Writer::firstOrNew([
            'name' => $this->data['name'],
            'bio' => $this->data['bio'],
            'birth_date' => $this->data['birth_date'],
            'death_date' => $this->data['death_date'],
            'birth_place' => $this->data['birth_place'],
            'death_place' => $this->data['death_place'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'İçeri aktarım tamamlandı. ' . number_format($import->successful_rows) . ' yazar eklendi.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' yazar içeri aktarılamadı.';
        }

        return $body;
    }
}
