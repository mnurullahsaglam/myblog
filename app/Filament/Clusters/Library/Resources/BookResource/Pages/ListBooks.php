<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Pages;

use App\Filament\Clusters\Library\Imports\BookImporter;
use App\Filament\Clusters\Library\Resources\BookResource;
use App\Filament\Clusters\Library\Resources\BookResource\Widgets\BooksOverview;
use App\Filament\Exports\BookExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
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
                ->color('info'),
            ExportAction::make()
                ->exporter(BookExporter::class)
                ->color('success'),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BooksOverview::class,
        ];
    }
}
