<?php

declare(strict_types=1);

namespace App\Filament\Clusters\General\Resources\CategoryResource\Pages;

use App\Filament\Clusters\General\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
