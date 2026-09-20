<?php

declare(strict_types=1);

namespace App\Actions\Library;

use Illuminate\Database\Eloquent\Model;

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
