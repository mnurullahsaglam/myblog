<?php

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency->getSymbol() . ' ' . number_format($this->amount, 2);
    }

    public function getSourceAttribute(): string
    {
        if ($this->relationLoaded('client') && $this->client) {
            return $this->client->title;
        } elseif ($this->relationLoaded('invoice') && $this->invoice) {
            return 'Invoice #' . $this->invoice->id;
        } elseif ($this->relationLoaded('debt') && $this->debt) {
            return 'Debt from ' . $this->debt->creditor_name;
        }

        // Fallback: try to load relationships if not already loaded
        if ($this->client_id && !$this->relationLoaded('client')) {
            return optional($this->client)->title ?? 'Client';
        } elseif ($this->invoice_id && !$this->relationLoaded('invoice')) {
            return 'Invoice #' . $this->invoice_id;
        } elseif ($this->debt_id && !$this->relationLoaded('debt')) {
            return 'Debt Payment';
        }

        return 'Other';
    }
}
