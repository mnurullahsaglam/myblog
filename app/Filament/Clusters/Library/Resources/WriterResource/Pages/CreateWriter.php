<?php

namespace App\Filament\Clusters\Library\Resources\WriterResource\Pages;

use App\Filament\Clusters\Library\Resources\WriterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWriter extends CreateRecord
{
    protected static string $resource = WriterResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
