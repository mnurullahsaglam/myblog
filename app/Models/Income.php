<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ability;
use App\Enums\Currencies;
use App\Support\Access\AccessProfile;
use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int|null $client_id
 * @property int|null $income_category_id
 * @property int|null $invoice_id
 * @property int|null $debt_id
 * @property numeric-string $amount
 * @property Currencies $currency
 * @property string $description
 * @property Carbon $date
 * @property-read Client|null $client
 * @property-read IncomeCategory|null $incomeCategory
 * @property-read Invoice|null $invoice
 * @property-read Debt|null $debt
 * @property-read string $formatted_amount
 * @property-read string $source
 */
final class Income extends Model
{
    /** @use HasFactory<IncomeFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'client_id',
        'income_category_id',
        'invoice_id',
        'debt_id',
        'amount',
        'currency',
        'description',
        'date',
    ];

    #[Override]
    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => Currencies::class,
        'date' => 'date',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<IncomeCategory, $this>
     */
    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Debt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    protected function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol().' '.number_format((float) $this->amount, 2);
    }

    /**
     * A label for where the money came from.
     *
     * Derived rather than stored, which is why the check belongs here: the table,
     * the form, the show page and anything else that reads it would each have to
     * remember otherwise, and one of them would not. An income from a client
     * reads generically for anyone who may not see which client it was.
     */
    protected function getSourceAttribute(): string
    {
        if ($this->client_id !== null && ! resolve(AccessProfile::class)->allows(Ability::SeeClientIdentity)) {
            return 'Client work';
        }

        if ($this->relationLoaded('client') && $this->client) {
            return $this->client->title;
        }

        if ($this->relationLoaded('invoice') && $this->invoice) {
            return 'Invoice #'.$this->invoice->id;
        }

        if ($this->relationLoaded('debt') && $this->debt) {
            return 'Debt from '.$this->debt->creditor_name;
        }

        if ($this->client_id && ! $this->relationLoaded('client')) {
            return $this->client->title ?? 'Client';
        }

        if ($this->invoice_id && ! $this->relationLoaded('invoice')) {
            return 'Invoice #'.$this->invoice_id;
        }

        if ($this->debt_id && ! $this->relationLoaded('debt')) {
            return 'Debt Payment';
        }

        return 'Other';
    }
}
