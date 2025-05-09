<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Pages;

use App\Filament\Clusters\Library\Resources\BookResource;
use App\Traits\RedirectToList;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBook extends EditRecord
{
    use RedirectToList;

    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
