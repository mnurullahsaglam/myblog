<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IncomeCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 */
class IncomeCategory extends Model
{
    /** @use HasFactory<IncomeCategoryFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'name',
        'description',
        'color',
    ];

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
