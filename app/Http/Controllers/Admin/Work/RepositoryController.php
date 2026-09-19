<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Forms\Definitions\RepositoryForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\RepositoryRequest;
use App\Models\Repository;
use App\Tables\Definitions\RepositoryTable;

final class RepositoryController extends AdminResourceController
{
    protected function table(): RepositoryTable
    {
        return new RepositoryTable;
    }

    protected function form(): RepositoryForm
    {
        return new RepositoryForm;
    }

    protected function modelClass(): string
    {
        return Repository::class;
    }

    protected function resourceName(): string
    {
        return 'repositories';
    }

    protected function pagePath(): string
    {
        return 'Work/Repositories';
    }

    protected function requestClass(): string
    {
        return RepositoryRequest::class;
    }
}
