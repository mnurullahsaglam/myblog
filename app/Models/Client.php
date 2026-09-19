<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $email
 * @property string $country
 * @property string $address
 * @property string $tax_no
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Income> $incomes
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
