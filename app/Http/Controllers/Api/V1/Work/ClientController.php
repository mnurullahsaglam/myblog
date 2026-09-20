<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Forms\Definitions\ClientForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\ClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Tables\Definitions\ClientTable;

final class ClientController extends ApiResourceController
{
    protected function table(): ClientTable
    {
        return new ClientTable;
    }

    protected function form(): ClientForm
    {
        return new ClientForm;
    }

    protected function modelClass(): string
    {
        return Client::class;
    }

    protected function resourceName(): string
    {
        return 'clients';
    }

    protected function requestClass(): string
    {
        return ClientRequest::class;
    }

    protected function resourceClass(): string
    {
        return ClientResource::class;
    }
}
