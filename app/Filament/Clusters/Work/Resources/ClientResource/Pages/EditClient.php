<?php

namespace App\Filament\Clusters\Work\Resources\ClientResource\Pages;

use App\Filament\Clusters\Work\Resources\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
