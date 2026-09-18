<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Forms\Definitions\PublisherForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\PublisherRequest;
use App\Models\Publisher;
use App\Tables\Definitions\PublisherTable;
use App\Tables\ResourceTable;

class PublisherController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new PublisherTable;
    }

    protected function form(): ResourceForm
    {
        return new PublisherForm;
    }

    protected function modelClass(): string
    {
        return Publisher::class;
    }

    protected function resourceName(): string
    {
        return 'publishers';
    }

    protected function pagePath(): string
    {
        return 'Library/Publishers';
    }

    protected function requestClass(): string
    {
        return PublisherRequest::class;
    }
}
