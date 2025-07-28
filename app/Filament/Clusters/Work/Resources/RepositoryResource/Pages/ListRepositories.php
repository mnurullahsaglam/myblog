<?php

namespace App\Filament\Clusters\Work\Resources\RepositoryResource\Pages;

use App\Filament\Clusters\Work\Resources\RepositoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepositories extends ListRecords
{
    protected static string $resource = RepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 