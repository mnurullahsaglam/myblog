<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Forms\Definitions\PublisherForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\PublisherRequest;
use App\Models\Publisher;
use App\Tables\Definitions\PublisherTable;

final class PublisherController extends AdminResourceController
{
    protected function table(): PublisherTable
    {
        return new PublisherTable;
    }

    protected function form(): PublisherForm
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
