<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UtilityType;
use Database\Factories\UtilityAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property UtilityType $type
 * @property string $label
 */
final class UtilityAccount extends Model
{
    /** @use HasFactory<UtilityAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => UtilityType::class, 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<UtilityBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(UtilityBill::class);
    }
}
