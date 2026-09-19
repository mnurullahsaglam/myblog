<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Forms\Definitions\ClientForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\ClientRequest;
use App\Models\Client;
use App\Tables\Definitions\ClientTable;
use App\Tables\ResourceTable;

class ClientController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new ClientTable;
    }

    protected function form(): ResourceForm
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
