<?php

namespace App\Filament\Clusters\Library\Resources\WriterResource\Pages;

use App\Filament\Clusters\Library\Imports\WriterImporter;
use App\Filament\Clusters\Library\Resources\WriterResource;
use App\Filament\Exports\WriterExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListWriters extends ListRecords
{
    protected static string $resource = WriterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(WriterImporter::class)
                ->label('İçe Aktar: Yazarlar'),
            ExportAction::make()
                ->exporter(WriterExporter::class)
                ->fileName(fn(Export $export): string => 'Okuma Grubu Yazar Listesi')
                ->label('Dışa Aktar: Yazarlar'),
            CreateAction::make(),
        ];
    }
}