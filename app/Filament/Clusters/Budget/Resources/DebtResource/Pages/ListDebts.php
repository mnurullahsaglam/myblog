<?php

namespace App\Filament\Clusters\Budget\Resources\DebtResource\Pages;

use App\Filament\Clusters\Budget\Resources\DebtResource;
use App\Filament\Clusters\Budget\Resources\DebtResource\Widgets\ExchangeRateWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDebts extends ListRecords
{
    protected static string $resource = DebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExchangeRateWidget::class,
        ];
    }
} 