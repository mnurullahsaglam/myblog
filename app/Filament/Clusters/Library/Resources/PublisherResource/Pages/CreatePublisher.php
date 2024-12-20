<?php

namespace App\Filament\Clusters\Library\Resources\PublisherResource\Pages;

use App\Filament\Clusters\Library\Resources\PublisherResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublisher extends CreateRecord
{
    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
