<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Forms\Definitions\DebtForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\DebtRequest;
use App\Http\Resources\Api\V1\DebtResource;
use App\Models\Debt;
use App\Tables\Definitions\DebtTable;

final class DebtController extends ApiResourceController
{
    protected function table(): DebtTable
    {
        return new DebtTable;
    }

    protected function form(): DebtForm
    {
        return new DebtForm;
    }

    protected function modelClass(): string
    {
        return Debt::class;
    }

    protected function resourceName(): string
    {
        return 'debts';
    }

    protected function requestClass(): string
    {
        return DebtRequest::class;
    }

    protected function resourceClass(): string
    {
        return DebtResource::class;
    }
}
