<?php

namespace App\Filament\Clusters\Library\Resources\BookResource\Widgets;

use App\Models\Book;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class BooksOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('Book count'), $this->getBookCount()),
            Stat::make(__('Writer count'), $this->getWriterCount()),
            Stat::make(__('Publisher count'), $this->getPublisherCount()),
            Stat::make(__('Page count'), $this->getPageCount()),
        ];
    }

    private function getBookCount(): int
    {
        return Book::count();
    }

    private function getWriterCount(): int
    {
        return DB::table('books')->distinct('writer_id')->count();
    }

    private function getPublisherCount(): int
    {
        return DB::table('books')->distinct('publisher_id')->count();
    }

    private function getPageCount(): int
    {
        return Book::sum('page_count');
    }
}
