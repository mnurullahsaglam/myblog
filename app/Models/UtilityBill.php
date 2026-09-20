<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Database\Factories\UtilityBillFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Currencies $currency
 * @property float|null $consumption
 * @property float|null $meter_start
 * @property float|null $meter_end
 * @property float $total_amount
 * @property Carbon $due_date
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $paid_at
 */
final class UtilityBill extends Model
{
    /** @use HasFactory<UtilityBillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currencies::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'meter_start' => 'float',
            'meter_end' => 'float',
            'total_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<UtilityAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(UtilityAccount::class, 'utility_account_id');
    }

    /**
     * @return HasMany<UtilityBillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(UtilityBillLine::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Never stored: a saved copy drifts from the readings it claims to
     * summarise. Negative when a meter was replaced mid-period, which is
     * information rather than an error.
     *
     * @return Attribute<float|null, never>
     */
    protected function consumption(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->meter_start === null || $this->meter_end === null
            ? null
            : round($this->meter_end - $this->meter_start, 3));
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * What the breakdown adds up to, which may differ from the stated total by
     * rounding. The form warns on a real difference; nothing blocks on it.
     */
    public function lineTotal(): float
    {
        $total = $this->lines->sum(fn (UtilityBillLine $line): float => $line->amount);

        return round($total, 2);
    }
}
