<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Utilities;

use App\Forms\Definitions\UtilityBillForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\UtilityBillRequest;
use App\Http\Resources\Api\V1\UtilityBillResource;
use App\Models\UtilityBill;
use App\Tables\Definitions\UtilityBillTable;

final class UtilityBillController extends ApiResourceController
{
    protected function table(): UtilityBillTable
    {
        return new UtilityBillTable;
    }

    protected function form(): UtilityBillForm
    {
        return new UtilityBillForm;
    }

    protected function modelClass(): string
    {
        return UtilityBill::class;
    }

    protected function resourceName(): string
    {
        return 'utility-bills';
    }

    protected function requestClass(): string
    {
        return UtilityBillRequest::class;
    }

    protected function resourceClass(): string
    {
        return UtilityBillResource::class;
    }
}
