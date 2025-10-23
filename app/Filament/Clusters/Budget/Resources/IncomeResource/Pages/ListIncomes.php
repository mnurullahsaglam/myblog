<?php

namespace App\Filament\Clusters\Budget\Resources\IncomeResource\Pages;

use App\Filament\Clusters\Budget\Resources\IncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIncomes extends ListRecords
{
    protected static string $resource = IncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IncomeResource\Widgets\IncomeOverview::class,
        ];
    }
} 