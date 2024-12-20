<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Pages;

use App\Filament\Clusters\Library\Resources\BookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
