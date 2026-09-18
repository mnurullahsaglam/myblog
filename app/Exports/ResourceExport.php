<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class ResourceExport
{
    /**
     * @return array<int, string>
     */
    abstract public function headings(): array;

    /**
     * @return Builder<covariant Model>
     */
    abstract public function query(): Builder;

    /**
     * @return array<int, string|int|float|null>
     */
    abstract public function row(Model $record): array;

    abstract public function name(): string;

    public function filename(): string
    {
        return 'exports/'.$this->name().'-'.now()->format('Ymd-His').'.csv';
    }
}
