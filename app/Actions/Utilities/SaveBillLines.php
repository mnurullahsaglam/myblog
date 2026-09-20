<?php

declare(strict_types=1);

namespace App\Actions\Utilities;

use App\Models\UtilityBill;
use Illuminate\Support\Facades\DB;

/**
 * Replace a bill's breakdown with the rows the form submitted.
 *
 * Delete and recreate rather than diffing: the rows have no identity worth
 * preserving, and diffing would be more code for the same result. One
 * transaction, because a half-written breakdown is worse than the old one -
 * you would not know which rows were yours.
 */
final class SaveBillLines
{
    /**
     * @param  array<int, array{label?: string|null, amount?: float|int|string|null}>  $lines
     * @return int the number of lines stored
     */
    public function handle(UtilityBill $bill, array $lines): int
    {
        return DB::transaction(function () use ($bill, $lines): int {
            $bill->lines()->delete();

            foreach (array_values($lines) as $index => $line) {
                $bill->lines()->create([
                    'label' => $line['label'] ?? null,
                    'amount' => $line['amount'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return count($lines);
        });
    }
}
