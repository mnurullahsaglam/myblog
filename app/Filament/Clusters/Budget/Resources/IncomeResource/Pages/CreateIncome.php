<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Budget\Resources\IncomeResource\Pages;

use App\Filament\Clusters\Budget\Resources\IncomeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncome extends CreateRecord
{
    protected static string $resource = IncomeResource::class;
}
