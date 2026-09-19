<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Budget;

use App\Actions\Budget\PayDebt;
use App\Enums\Currencies;
use App\Forms\Definitions\DebtForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\DebtRequest;
use App\Http\Requests\Admin\PayDebtRequest;
use App\Models\Debt;
use App\Tables\Definitions\DebtTable;
use Illuminate\Http\RedirectResponse;

final class DebtController extends AdminResourceController
{
    protected function table(): DebtTable
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

    protected function form(): DebtForm
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
    public function pay(PayDebtRequest $request, Debt $debt, PayDebt $payDebt): RedirectResponse
    {
        /** @var array{payment_amount: numeric-string|float|int, payment_description: string} $data */
        $data = $request->validated();

        $payment = (float) $data['payment_amount'];

        $stored = $request->hasFile('receipt')
            ? $request->file('receipt')?->store('receipts/debt-payments', 'public')
            : null;

        $receiptPath = is_string($stored) ? $stored : null;

        $remaining = $payDebt->handle($debt, $payment, $data['payment_description'], $receiptPath);

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
