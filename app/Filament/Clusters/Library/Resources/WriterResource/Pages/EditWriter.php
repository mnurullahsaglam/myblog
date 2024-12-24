<?php

namespace App\Filament\Clusters\Library\Resources\WriterResource\Pages;

use App\Filament\Clusters\Library\Resources\WriterResource;
use App\Traits\RedirectToList;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWriter extends EditRecord
{
    use RedirectToList;

    protected static string $resource = WriterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
