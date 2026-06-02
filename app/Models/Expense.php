<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $expense_category_id
 * @property int|null $debt_id
 * @property numeric-string $amount
 * @property Currencies $currency
 * @property string $description
 * @property string|null $receipt_path
 * @property \Illuminate\Support\Carbon $date
 * @property-read ExpenseCategory|null $expenseCategory
 * @property-read Debt|null $debt
 * @property-read string $formatted_amount
 * @property-read bool $has_receipt
 * @property-read string|null $receipt_url
 */
class Expense extends Model
{
    protected $fillable = [
        'expense_category_id',
        'debt_id',
        'amount',
        'currency',
        'description',
        'receipt_path',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => Currencies::class,
        'date' => 'date',
    ];

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<Debt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol().' '.number_format((float) $this->amount, 2);
    }

    public function getHasReceiptAttribute(): bool
    {
        return ! is_null($this->receipt_path);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt_path ? asset('storage/'.$this->receipt_path) : null;
    }
}
