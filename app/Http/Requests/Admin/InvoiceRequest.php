<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Currencies;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
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
