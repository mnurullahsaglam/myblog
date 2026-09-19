<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

final class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with('writer')->paginate(12);

        return view('pages.books.index', compact('books'));
    }
}
