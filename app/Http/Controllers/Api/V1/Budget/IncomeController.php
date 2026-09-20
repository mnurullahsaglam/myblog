<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Enums\Ability;
use App\Forms\Definitions\IncomeForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\IncomeRequest;
use App\Http\Resources\Api\V1\IncomeResource;
use App\Models\Income;
use App\Support\Access\AccessProfile;
use App\Tables\Definitions\IncomeTable;
use Illuminate\Database\Eloquent\Model;
use Override;

final class IncomeController extends ApiResourceController
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

    protected function requestClass(): string
    {
        return IncomeRequest::class;
    }

    protected function resourceClass(): string
    {
        return IncomeResource::class;
    }

    /**
     * The same rule the panel applies: an income that names a client is readable
     * but not writable by anyone who may not see which client it is.
     */
    #[Override]
    protected function isRecordEditable(Model $record): bool
    {
        if (app(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
            return true;
        }

        return $record->getAttribute('client_id') === null;
    }
}
