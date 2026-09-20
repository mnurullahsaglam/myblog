<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Forms\Definitions\ExpenseForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\Expense;
use App\Tables\Definitions\ExpenseTable;

final class ExpenseController extends ApiResourceController
{
    protected function table(): ExpenseTable
    {
        return new ExpenseTable;
    }

    protected function form(): ExpenseForm
    {
        return new ExpenseForm;
    }

    protected function modelClass(): string
    {
        return Expense::class;
    }

    protected function resourceName(): string
    {
        return 'expenses';
    }

    protected function requestClass(): string
    {
        return ExpenseRequest::class;
    }

    protected function resourceClass(): string
    {
        return ExpenseResource::class;
    }
}
