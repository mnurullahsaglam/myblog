<?php

namespace App\Filament\Clusters\Work\Resources\RepositoryResource\Pages;

use App\Filament\Clusters\Work\Resources\RepositoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRepository extends EditRecord
{
    protected static string $resource = RepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
} 