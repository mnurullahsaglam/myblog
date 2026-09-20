<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UtilityBillLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property float $amount
 */
final class UtilityBillLine extends Model
{
    /** @use HasFactory<UtilityBillLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    /**
     * @return BelongsTo<UtilityBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(UtilityBill::class, 'utility_bill_id');
    }
}
