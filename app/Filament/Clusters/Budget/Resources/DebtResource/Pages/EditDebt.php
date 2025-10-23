<?php

namespace App\Filament\Clusters\Budget\Resources\DebtResource\Pages;

use App\Filament\Clusters\Budget\Resources\DebtResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDebt extends EditRecord
{
    protected static string $resource = DebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
} 