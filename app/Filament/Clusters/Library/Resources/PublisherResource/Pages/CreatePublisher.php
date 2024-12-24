<?php

namespace App\Filament\Clusters\Library\Resources\PublisherResource\Pages;

use App\Filament\Clusters\Library\Resources\PublisherResource;
use App\Traits\RedirectToList;
use Filament\Resources\Pages\CreateRecord;

class CreatePublisher extends CreateRecord
{
    use RedirectToList;

    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
