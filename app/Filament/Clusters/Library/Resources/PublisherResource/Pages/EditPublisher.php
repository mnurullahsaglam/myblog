<?php

namespace App\Filament\Clusters\Library\Resources\PublisherResource\Pages;

use App\Filament\Clusters\Library\Resources\PublisherResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublisher extends EditRecord
{
    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
