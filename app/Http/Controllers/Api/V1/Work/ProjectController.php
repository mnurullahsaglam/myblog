<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Forms\Definitions\ProjectForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\ProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Tables\Definitions\ProjectTable;

final class ProjectController extends ApiResourceController
{
    protected function table(): ProjectTable
    {
        return new ProjectTable;
    }

    protected function form(): ProjectForm
    {
        return new ProjectForm;
    }

    protected function modelClass(): string
    {
        return Project::class;
    }

    protected function resourceName(): string
    {
        return 'projects';
    }

    protected function requestClass(): string
    {
        return ProjectRequest::class;
    }

    protected function resourceClass(): string
    {
        return ProjectResource::class;
    }
}
