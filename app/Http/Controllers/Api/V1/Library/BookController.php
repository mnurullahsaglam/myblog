<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Library;

use App\Forms\Definitions\BookForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\BookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use App\Tables\Definitions\BookTable;

final class BookController extends ApiResourceController
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

    protected function requestClass(): string
    {
        return BookRequest::class;
    }

    protected function resourceClass(): string
    {
        return BookResource::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['image' => 'books'];
    }
}
