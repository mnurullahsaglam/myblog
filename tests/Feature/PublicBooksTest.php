<?php

declare(strict_types=1);

use App\Models\Book;

it('lists books on the public page', function (): void {
    $books = Book::factory()->count(3)->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertSee($books->first()->name);
});

it('paginates at twelve books a page', function (): void {
    Book::factory()->count(13)->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertSee('page=2');
});

it('rejects a write to the books collection', function (): void {
    $this->post('/books')->assertMethodNotAllowed();
});

it('no longer routes any per-book URI', function (string $method): void {
    $this->call($method, '/books/1')->assertNotFound();
})->with(['GET', 'PUT', 'PATCH', 'DELETE']);

it('no longer exposes a create form', function (): void {
    $this->get('/books/create')->assertNotFound();
});

it('no longer exposes a public show page', function (): void {
    $book = Book::factory()->create();

    $this->get('/books/'.$book->getKey())->assertNotFound();
});
