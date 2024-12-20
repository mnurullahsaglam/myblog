<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Pages;

use App\Filament\Clusters\Library\Imports\BookImporter;
use App\Filament\Clusters\Library\Resources\BookResource;
use App\Filament\Exports\BookExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(BookImporter::class)
                ->color('info')
                ->label('İçe Aktar: Kitaplar'),
            ExportAction::make()
                ->exporter(BookExporter::class)
                ->color('success')
                ->label('Dışa Aktar: Kitaplar')
                ->fileName(fn(Export $export): string => 'Kitap Listesi'),
            CreateAction::make(),
        ];
    }
}
