<?php

namespace App\Filament\Clusters\Library\Resources\WriterResource\Pages;

use App\Filament\Clusters\Library\Resources\WriterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWriter extends EditRecord
{
    protected static string $resource = WriterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
