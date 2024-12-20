<?php

namespace App\Filament\Clusters\Library\Resources\PublisherResource\Pages;

use App\Filament\Clusters\Library\Imports\PublisherImporter;
use App\Filament\Clusters\Library\Resources\PublisherResource;
use App\Filament\Exports\PublisherExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListPublishers extends ListRecords
{
    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(PublisherImporter::class)
                ->color('info')
                ->label('İçe Aktar: Yayınevleri'),
            ExportAction::make()
                ->exporter(PublisherExporter::class)
                ->color('success')
                ->label('Dışa Aktar: Yayınevleri')
                ->fileName(fn(Export $export): string => 'Yayınevi Listesi'),
            CreateAction::make(),
        ];
    }
}
