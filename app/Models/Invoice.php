<?php

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'currency' => Currencies::class,
        ];
    }

    protected function amountWithCurrencySymbol(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->currency->getSymbol() . number_format($this->amount, 2, ',', '.'),
        );
    }
}
