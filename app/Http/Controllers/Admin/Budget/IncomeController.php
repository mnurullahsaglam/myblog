<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Forms\Definitions\IncomeForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\IncomeRequest;
use App\Models\Income;
use App\Tables\Definitions\IncomeTable;

final class IncomeController extends AdminResourceController
{
    protected function table(): IncomeTable
    {
        return new IncomeTable;
    }

    protected function form(): IncomeForm
    {
        return new IncomeForm;
    }

    protected function modelClass(): string
    {
        return Income::class;
    }

    protected function resourceName(): string
    {
        return 'incomes';
    }

    protected function pagePath(): string
    {
        return 'Budget/Incomes';
    }

    protected function requestClass(): string
    {
        return IncomeRequest::class;
    }
}
