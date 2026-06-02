<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $client_id
 * @property int|null $income_category_id
 * @property int|null $invoice_id
 * @property int|null $debt_id
 * @property numeric-string $amount
 * @property Currencies $currency
 * @property string $description
 * @property \Illuminate\Support\Carbon $date
 * @property-read Client|null $client
 * @property-read IncomeCategory|null $incomeCategory
 * @property-read Invoice|null $invoice
 * @property-read Debt|null $debt
 * @property-read string $formatted_amount
 * @property-read string $source
 */
class Income extends Model
{
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

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol().' '.number_format((float) $this->amount, 2);
    }

    public function getSourceAttribute(): string
    {
        if ($this->relationLoaded('client') && $this->client) {
            return $this->client->title;
        } elseif ($this->relationLoaded('invoice') && $this->invoice) {
            return 'Invoice #'.$this->invoice->id;
        } elseif ($this->relationLoaded('debt') && $this->debt) {
            return 'Debt from '.$this->debt->creditor_name;
        }

        // Fallback: try to load relationships if not already loaded
        if ($this->client_id && ! $this->relationLoaded('client')) {
            return $this->client->title ?? 'Client';
        } elseif ($this->invoice_id && ! $this->relationLoaded('invoice')) {
            return 'Invoice #'.$this->invoice_id;
        } elseif ($this->debt_id && ! $this->relationLoaded('debt')) {
            return 'Debt Payment';
        }

        return 'Other';
    }
}
