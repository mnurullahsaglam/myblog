<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\NotifiesAdmin;
use App\Models\Debt;
use App\Models\Expense;

final readonly class DebtObserver
{
    public function __construct(private NotifiesAdmin $notifier) {}

    public function updated(Debt $debt): void
    {
        if ($debt->isDirty('status') && $debt->status === 'paid') {
            $this->createExpenseFromDebtPayment($debt);
        }
    }

    private function createExpenseFromDebtPayment(Debt $debt): void
    {
        $existingExpense = Expense::where('debt_id', $debt->id)->first();

        if ($existingExpense) {
            return;
        }

        Expense::create([
            'debt_id' => $debt->id,
            'amount' => $debt->amount,
            'currency' => $debt->currency->value,
            'description' => "Debt payment to {$debt->creditor_name} - {$debt->description}",
            'date' => now()->toDateString(),
        ]);

        $this->notifier->success(
            'Expense Created',
            "Expense record created for debt payment to {$debt->creditor_name}",
        );
    }
}
