<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Utilities;

use App\Forms\Definitions\UtilityAccountForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\UtilityAccountRequest;
use App\Http\Resources\Api\V1\UtilityAccountResource;
use App\Models\UtilityAccount;
use App\Tables\Definitions\UtilityAccountTable;

final class UtilityAccountController extends ApiResourceController
{
    protected function table(): UtilityAccountTable
    {
        return new UtilityAccountTable;
    }

    protected function form(): UtilityAccountForm
    {
        return new UtilityAccountForm;
    }

    protected function modelClass(): string
    {
        return UtilityAccount::class;
    }

    protected function resourceName(): string
    {
        return 'utility-accounts';
    }

    protected function requestClass(): string
    {
        return UtilityAccountRequest::class;
    }

    protected function resourceClass(): string
    {
        return UtilityAccountResource::class;
    }
}
