<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Forms\Definitions\InvoiceForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Tables\Definitions\InvoiceTable;

final class InvoiceController extends ApiResourceController
{
    protected function table(): InvoiceTable
    {
        return new InvoiceTable;
    }

    protected function form(): InvoiceForm
    {
        return new InvoiceForm;
    }

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function resourceName(): string
    {
        return 'invoices';
    }

    protected function requestClass(): string
    {
        return InvoiceRequest::class;
    }

    protected function resourceClass(): string
    {
        return InvoiceResource::class;
    }
}
