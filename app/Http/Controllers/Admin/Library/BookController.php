<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Forms\Definitions\BookForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Support\Widgets\LibraryOverview;
use App\Tables\Definitions\BookTable;
use App\Tables\ResourceTable;

class BookController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new BookTable;
    }

    protected function form(): ResourceForm
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
     * The library summary sits above the books table.
     *
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
