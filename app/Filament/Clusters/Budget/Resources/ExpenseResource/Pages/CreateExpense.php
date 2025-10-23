<?php

namespace App\Filament\Clusters\Budget\Resources\ExpenseResource\Pages;

use App\Filament\Clusters\Budget\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
} 