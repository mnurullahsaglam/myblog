<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Forms\Definitions\ClientForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\ClientRequest;
use App\Models\Client;
use App\Tables\Definitions\ClientTable;

final class ClientController extends AdminResourceController
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

    protected function pagePath(): string
    {
        return 'Work/Clients';
    }

    protected function requestClass(): string
    {
        return ClientRequest::class;
    }
}
