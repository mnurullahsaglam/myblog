<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Enums\Currencies;
use App\Models\Invoice;
use Illuminate\Validation\Rule;
use Override;

final class InvoiceRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Work;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : null;

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'invoice_number' => ['required', 'string', 'max:255', Rule::unique('invoices', 'invoice_number')->ignore($invoiceId)],
            'issued_at' => ['required', 'date'],
            'currency' => ['required', Rule::enum(Currencies::class)],
            'amount' => ['required', 'integer', 'min:0'],
            'tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'invoice' => [$invoiceId === null ? 'required' : 'nullable', 'file', 'mimes:zip', 'max:20480'],
        ];
    }
}
