<?php

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol() . ' ' . number_format($this->amount, 2);
    }

    public function getHasReceiptAttribute(): bool
    {
        return !is_null($this->receipt_path);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt_path ? asset('storage/' . $this->receipt_path) : null;
    }
}
