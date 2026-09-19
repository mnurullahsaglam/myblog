<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Forms\Definitions\InvoiceForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Models\Invoice;
use App\Tables\Definitions\InvoiceTable;
use App\Tables\ResourceTable;
use Illuminate\Http\UploadedFile;

class InvoiceController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new InvoiceTable;
    }

    protected function form(): ResourceForm
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

    protected function pagePath(): string
    {
        return 'Work/Invoices';
    }

    protected function requestClass(): string
    {
        return InvoiceRequest::class;
    }

    /**
     * Tax and total are recomputed here rather than taken from input, so the
     * stored figures cannot disagree with the net amount and rate.
     *
     * The archive goes to the private disk: invoices are not public files.
     *
     * @return array<string, mixed>
     */
    protected function validated(): array
    {
        $request = app(InvoiceRequest::class);

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $amount = is_numeric($data['amount'] ?? null) ? (int) $data['amount'] : 0;
        $taxRate = is_numeric($data['tax_rate'] ?? null) ? (int) $data['tax_rate'] : 0;
        $taxAmount = (int) round($amount * $taxRate / 100);

        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = $amount + $taxAmount;

        $file = $request->file('invoice');

        if ($file instanceof UploadedFile) {
            $data['invoice'] = $file->store('invoices', 'local');
        } else {
            unset($data['invoice']);
        }

        return $data;
    }
}
