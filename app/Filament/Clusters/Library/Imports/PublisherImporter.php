<?php

namespace App\Filament\Clusters\Library\Imports;

use App\Models\Publisher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class PublisherImporter extends Importer
{
    protected static ?string $model = Publisher::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('İsim')
                ->requiredMapping()
                ->rules(['required', 'string', 'min:3', 'max:255'])
                ->exampleHeader('İsim'),
        ];
    }

    public function resolveRecord(): ?Publisher
    {
        return Publisher::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'İçeri aktarım tamamlandı. ' . number_format($import->successful_rows) . ' yayınevi eklendi.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' yayınevi içeri aktarılamadı.';
        }

        return $body;
    }
}
