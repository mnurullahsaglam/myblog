<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Debt;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

final class PayDebt
{
    /**
     * @return float the remaining balance, rounded to two places
     */
    public function handle(Debt $debt, float $payment, string $description, ?string $receiptPath = null): float
    {
        $remaining = round((float) $debt->amount - $payment, 2);

        DB::transaction(function () use ($debt, $payment, $description, $receiptPath, $remaining): void {
            Expense::create([
                'debt_id' => $debt->id,
                'amount' => $payment,
                'currency' => $debt->currency->value,
                'description' => $description,
                'receipt_path' => $receiptPath,
                'date' => now()->toDateString(),
            ]);

            $debt->update($remaining <= 0
                ? ['amount' => 0, 'status' => 'paid']
                : ['amount' => $remaining]);
        });

        return $remaining;
    }
}
