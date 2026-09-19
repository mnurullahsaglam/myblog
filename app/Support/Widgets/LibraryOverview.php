<?php

declare(strict_types=1);

namespace App\Support\Widgets;

use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;

final class LibraryOverview
{
    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    public static function stats(): array
    {
        $books = Book::query()->count();
        $pages = (int) Book::query()->sum('page_count');

        return [
            [
                'label' => 'Books',
                'value' => number_format($books),
                'caption' => $books === 0 ? 'nothing catalogued' : null,
                'icon' => 'pi pi-book',
            ],
            [
                'label' => 'Writers',
                'value' => number_format(Writer::query()->count()),
                'caption' => number_format(Writer::query()->has('books')->count()).' with a book',
                'icon' => 'pi pi-user',
            ],
            [
                'label' => 'Publishers',
                'value' => number_format(Publisher::query()->count()),
                'caption' => null,
                'icon' => 'pi pi-building',
            ],
            [
                'label' => 'Pages',
                'value' => number_format($pages),
                'caption' => $books > 0 ? number_format((int) round($pages / $books)).' per book' : null,
                'icon' => 'pi pi-file',
            ],
        ];
    }
}
