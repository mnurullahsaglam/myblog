<?php

namespace App\Filament\Clusters\Budget\Resources\ExpenseResource\Pages;

use App\Filament\Clusters\Budget\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 