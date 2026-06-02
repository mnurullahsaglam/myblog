<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property string $invoice_number
 * @property \Illuminate\Support\Carbon $issued_at
 * @property int $tax_rate
 * @property int $tax_amount
 * @property int $amount
 * @property int $total_amount
 * @property Currencies $currency
 * @property string $invoice
 * @property string|null $invoice_pdf
 * @property-read string $amount_with_currency_symbol
 * @property-read Client|null $client
 */
class Invoice extends Model
{
    /**
     * @return BelongsTo<Client, $this>
     */
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

    /**
     * @return Attribute<string, never>
     */
    protected function amountWithCurrencySymbol(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->currency->getSymbol().number_format($this->amount, 2, ',', '.'),
        );
    }
}
