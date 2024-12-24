<?php

namespace App\Filament\Clusters\Library\Resources\PublisherResource\Pages;

use App\Filament\Clusters\Library\Resources\PublisherResource;
use App\Traits\RedirectToList;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublisher extends EditRecord
{
    use RedirectToList;

    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
