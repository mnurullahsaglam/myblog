<?php

declare(strict_types=1);

namespace App\Actions\Utilities;

use App\Models\Expense;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Record that a utility bill was paid.
 *
 * Refusing an already-paid bill is what stops a double click double-counting
 * the month's spending, which is the whole reason the expense is created here
 * rather than left to the user to enter twice.
 */
final class PayBill
{
    public function handle(UtilityBill $bill): Expense
    {
        throw_if($bill->isPaid(), RuntimeException::class, 'This bill is already paid.');

        return DB::transaction(function () use ($bill): Expense {
            $expense = Expense::create([
                'amount' => $bill->total_amount,
                'currency' => $bill->currency->value,
                'description' => $this->describe($bill),
                'is_recurring' => true,
                'date' => now()->toDateString(),
            ]);

            $bill->update(['paid_at' => now(), 'expense_id' => $expense->id]);

            return $expense;
        });
    }

    private function describe(UtilityBill $bill): string
    {
        // loadMissing, not a bare access: the controller hands over a
        // route-bound bill, and Model::shouldBeStrict() turns a lazy load into
        // an exception outside production.
        $account = $bill->loadMissing('account')->account;
        $label = $account instanceof UtilityAccount ? trim($account->label) : '';

        if (! $bill->period_start instanceof Carbon || ! $bill->period_end instanceof Carbon) {
            return $label === '' ? 'Utility bill' : $label.' bill';
        }

        return trim(sprintf(
            '%s %s — %s',
            $label,
            $bill->period_start->toDateString(),
            $bill->period_end->toDateString(),
        ));
    }
}
