<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Debt;
use App\Models\Expense;
use App\Support\AdminNotifier;

class DebtObserver
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    /**
     * Handle the Debt "created" event.
     */
    public function created(Debt $debt): void
    {
        //
    }

    /**
     * Handle the Debt "updated" event.
     */
    public function updated(Debt $debt): void
    {
        // Check if status was changed to 'paid'
        if ($debt->isDirty('status') && $debt->status === 'paid') {
            $this->createExpenseFromDebtPayment($debt);
        }
    }

    /**
     * Create expense record when debt is marked as paid
     */
    private function createExpenseFromDebtPayment(Debt $debt): void
    {
        // Check if expense already exists for this debt
        $existingExpense = Expense::where('debt_id', $debt->id)->first();

        if ($existingExpense) {
            return; // Expense already created
        }

        // Create expense record
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

    /**
     * Handle the Debt "deleted" event.
     */
    public function deleted(Debt $debt): void
    {
        //
    }

    /**
     * Handle the Debt "restored" event.
     */
    public function restored(Debt $debt): void
    {
        //
    }

    /**
     * Handle the Debt "force deleted" event.
     */
    public function forceDeleted(Debt $debt): void
    {
        //
    }
}
