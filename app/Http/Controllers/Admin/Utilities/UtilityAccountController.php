<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Utilities;

use App\Forms\Definitions\UtilityAccountForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\UtilityAccountRequest;
use App\Models\UtilityAccount;
use App\Tables\Definitions\UtilityAccountTable;

final class UtilityAccountController extends AdminResourceController
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

    protected function pagePath(): string
    {
        return 'Utilities/Accounts';
    }

    protected function requestClass(): string
    {
        return UtilityAccountRequest::class;
    }
}
