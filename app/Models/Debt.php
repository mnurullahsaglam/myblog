<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use App\Observers\DebtObserver;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property string $creditor_name
 * @property string $creditor_type
 * @property numeric-string $amount
 * @property Currencies $currency
 * @property Carbon|null $due_date
 * @property string $status
 * @property string|null $description
 * @property Carbon $date
 * @property-read bool $is_pending
 * @property-read bool $is_overdue
 * @property-read int|null $days_until_due
 * @property-read string $formatted_amount
 */
#[ObservedBy([DebtObserver::class])]
final class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    #[Override]
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

    protected function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol().' '.number_format((float) $this->amount, 2);
    }

    protected function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    protected function getIsOverdueAttribute(): bool
    {
        return $this->isPending && $this->due_date && $this->due_date->isPast();
    }

    protected function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => $this->isOverdue ? 'danger' : 'warning',
            default => 'success',
        };
    }

    protected function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_date || ! $this->isPending) {
            return null;
        }

        return (int) now()->diffInDays($this->due_date, false);
    }

    protected function getHasDueDateAttribute(): bool
    {
        return ! is_null($this->due_date);
    }

    protected function getDueDateStatusAttribute(): string
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
        }

        if ($days === 0) {
            return 'Due today';
        }

        return $days.' days remaining';
    }
}
