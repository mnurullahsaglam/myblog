<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Enums\Ability;
use App\Forms\Definitions\IncomeForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\IncomeRequest;
use App\Models\Income;
use App\Support\Access\AccessProfile;
use App\Tables\Definitions\IncomeTable;
use Illuminate\Database\Eloquent\Model;
use Override;

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

    /**
     * An income that names a client is readable but not writable by anyone who
     * may not see which client it is, because a save from a form that omits the
     * field would quietly drop the association.
     */
    #[Override]
    protected function isRecordEditable(Model $record): bool
    {
        if (resolve(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
            return true;
        }

        return $record->getAttribute('client_id') === null;
    }
}
