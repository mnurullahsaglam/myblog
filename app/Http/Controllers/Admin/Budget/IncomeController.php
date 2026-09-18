<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Forms\Definitions\IncomeForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\IncomeRequest;
use App\Models\Income;
use App\Tables\Definitions\IncomeTable;
use App\Tables\ResourceTable;

class IncomeController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new IncomeTable;
    }

    protected function form(): ResourceForm
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
