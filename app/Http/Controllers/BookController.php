<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with('writer')->paginate(12);

        return view('pages.books.index', compact('books'));
    }

    public function create(): void {}

    public function store(Request $request): void {}

    public function show(Book $book): void {}

    public function edit(Book $book): void {}

    public function update(Request $request, Book $book): void {}

    public function destroy(Book $book): void {}
}
