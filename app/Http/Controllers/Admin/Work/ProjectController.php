<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Forms\Definitions\ProjectForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use App\Tables\Definitions\ProjectTable;

final class ProjectController extends AdminResourceController
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

    protected function pagePath(): string
    {
        return 'Work/Projects';
    }

    protected function requestClass(): string
    {
        return ProjectRequest::class;
    }
}
