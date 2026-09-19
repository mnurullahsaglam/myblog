<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Forms\Definitions\ExpenseForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Tables\Definitions\ExpenseTable;
use App\Tables\ResourceTable;

class ExpenseController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new ExpenseTable;
    }

    protected function form(): ResourceForm
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

    protected function pagePath(): string
    {
        return 'Budget/Expenses';
    }

    protected function requestClass(): string
    {
        return ExpenseRequest::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['receipt_path' => 'receipts'];
    }
}
