<?php

namespace App\Filament\Clusters\Work\Resources\ClientResource\Pages;

use App\Filament\Clusters\Work\Resources\ClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
