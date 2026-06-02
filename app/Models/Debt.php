<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use App\Observers\DebtObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $creditor_name
 * @property string $creditor_type
 * @property numeric-string $amount
 * @property Currencies $currency
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $status
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $date
 * @property-read bool $is_pending
 * @property-read bool $is_overdue
 * @property-read int|null $days_until_due
 * @property-read string $formatted_amount
 */
#[ObservedBy([DebtObserver::class])]
class Debt extends Model
{
    protected $fillable = [
        'creditor_name',
        'creditor_type',
        'amount',
        'currency',
        'due_date',
        'status',
        'description',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => Currencies::class,
        'due_date' => 'date',
        'date' => 'date',
    ];

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol().' '.number_format((float) $this->amount, 2);
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isPending && $this->due_date && $this->due_date->isPast();
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => $this->isOverdue ? 'danger' : 'warning',
            default => 'success',
        };
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_date || ! $this->isPending) {
            return null;
        }

        return (int) now()->diffInDays($this->due_date, false);
    }

    public function getHasDueDateAttribute(): bool
    {
        return ! is_null($this->due_date);
    }

    public function getDueDateStatusAttribute(): string
    {
        if (! $this->due_date) {
            return 'No due date';
        }

        if ($this->status === 'paid') {
            return 'Paid';
        }

        $days = $this->days_until_due;
        if ($days === null) {
            return 'No due date';
        }

        if ($days < 0) {
            return abs($days).' days overdue';
        } elseif ($days === 0) {
            return 'Due today';
        }

        return $days.' days remaining';
    }
}
