<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Forms\Definitions\BookForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Support\Widgets\LibraryOverview;
use App\Tables\Definitions\BookTable;

final class BookController extends AdminResourceController
{
    protected function table(): BookTable
    {
        return new BookTable;
    }

    protected function form(): BookForm
    {
        return new BookForm;
    }

    protected function modelClass(): string
    {
        return Book::class;
    }

    protected function resourceName(): string
    {
        return 'books';
    }

    protected function pagePath(): string
    {
        return 'Library/Books';
    }

    protected function requestClass(): string
    {
        return BookRequest::class;
    }

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    protected function indexTiles(): array
    {
        return LibraryOverview::stats();
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['image' => 'books'];
    }
}
