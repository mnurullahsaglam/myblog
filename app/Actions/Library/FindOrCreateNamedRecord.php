<?php

declare(strict_types=1);

namespace App\Actions\Library;

use Illuminate\Database\Eloquent\Model;

/**
 * Reuse a writer or publisher by name, or create one carrying just that name.
 *
 * Matching lowercases both sides explicitly rather than leaning on the
 * database's collation. MySQL compares case insensitively by default and SQLite
 * does not, so relying on collation would mean the tests and production
 * disagreed about whether "george orwell" is George Orwell. These tables hold
 * tens of rows, so the lost index is worth the certainty.
 *
 * Neither table has a unique index on name, so a tie goes to the lowest id.
 */
final class FindOrCreateNamedRecord
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function handle(string $modelClass, string $name): Model
    {
        $name = trim($name);

        $existing = $modelClass::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        return $modelClass::create(['name' => $name]);
    }
}
