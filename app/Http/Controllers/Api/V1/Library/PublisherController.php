<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Library;

use App\Forms\Definitions\PublisherForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\PublisherRequest;
use App\Http\Resources\Api\V1\PublisherResource;
use App\Models\Publisher;
use App\Tables\Definitions\PublisherTable;

final class PublisherController extends ApiResourceController
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

    protected function requestClass(): string
    {
        return PublisherRequest::class;
    }

    protected function resourceClass(): string
    {
        return PublisherResource::class;
    }
}
