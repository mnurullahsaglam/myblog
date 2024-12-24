<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Pages;

use App\Filament\Clusters\Library\Resources\BookResource;
use App\Traits\RedirectToList;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    use RedirectToList;

    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
