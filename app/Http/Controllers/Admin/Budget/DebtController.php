<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Enums\Currencies;
use App\Forms\Definitions\DebtForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\DebtRequest;
use App\Http\Requests\Admin\PayDebtRequest;
use App\Models\Debt;
use App\Models\Expense;
use App\Tables\Definitions\DebtTable;
use App\Tables\ResourceTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DebtController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new DebtTable($this->conversionCurrency());
    }

    /**
     * The table's converted-amount column reports in this currency. It arrives
     * through a display-only filter, so it never constrains the query.
     */
    private function conversionCurrency(): string
    {
        $requested = request()->input('filter.conversion_currency');

        return is_string($requested) && Currencies::tryFrom($requested) !== null
            ? $requested
            : Currencies::TRY->value;
    }

    protected function form(): ResourceForm
    {
        return new DebtForm;
    }

    protected function modelClass(): string
    {
        return Debt::class;
    }

    protected function resourceName(): string
    {
        return 'debts';
    }

    protected function pagePath(): string
    {
        return 'Budget/Debts';
    }

    protected function requestClass(): string
    {
        return DebtRequest::class;
    }

    /**
     * Record a payment against a debt, in full or in part.
     */
    public function pay(PayDebtRequest $request, Debt $debt): RedirectResponse
    {
        /** @var array{payment_amount: numeric-string|float|int, payment_description: string} $data */
        $data = $request->validated();

        $payment = (float) $data['payment_amount'];
        $remaining = round((float) $debt->amount - $payment, 2);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')?->store('receipts/debt-payments', 'public')
            : null;

        DB::transaction(function () use ($debt, $data, $payment, $remaining, $receiptPath): void {
            // Created before the status flips so DebtObserver finds it and does
            // not create a second expense for the same debt.
            Expense::create([
                'debt_id' => $debt->id,
                'amount' => $payment,
                'currency' => $debt->currency->value,
                'description' => $data['payment_description'],
                'receipt_path' => $receiptPath,
                'date' => now()->toDateString(),
            ]);

            $debt->update($remaining <= 0
                ? ['amount' => 0, 'status' => 'paid']
                : ['amount' => $remaining]);
        });

        $symbol = $debt->currency->getSymbol();

        if ($remaining <= 0) {
            $this->notifier->success('Debt settled', "Nothing further owed to {$debt->creditor_name}.");
        } else {
            $this->notifier->success(
                'Partial payment recorded',
                'Paid '.$symbol.number_format($payment, 2).'. Remaining '.$symbol.number_format($remaining, 2).'.',
            );
        }

        return to_route('admin.debts.index');
    }
}
