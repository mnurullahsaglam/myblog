<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IncomeCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 */
final class IncomeCategory extends Model
{
    /** @use HasFactory<IncomeCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
