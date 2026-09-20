<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Forms\Definitions\RepositoryForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\RepositoryRequest;
use App\Http\Resources\Api\V1\RepositoryResource;
use App\Models\Repository;
use App\Tables\Definitions\RepositoryTable;

final class RepositoryController extends ApiResourceController
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

    protected function requestClass(): string
    {
        return RepositoryRequest::class;
    }

    protected function resourceClass(): string
    {
        return RepositoryResource::class;
    }
}
