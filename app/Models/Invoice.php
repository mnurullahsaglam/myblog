<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currencies;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $invoice_number
 * @property Carbon $issued_at
 * @property int $tax_rate
 * @property int $tax_amount
 * @property int $amount
 * @property int $total_amount
 * @property Currencies $currency
 * @property string $invoice
 * @property-read string $amount_with_currency_symbol
 * @property-read Client|null $client
 */
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

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
