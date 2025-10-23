<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
    ];

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
