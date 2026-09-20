<?php

declare(strict_types=1);

use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\User;
use App\Models\Writer;
use App\Support\BookMetadata;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $stub = Mockery::mock(ResolvesIsbn::class);
    $stub->shouldReceive('resolve')->andReturn(new BookMetadata(
        title: 'Nineteen Eighty-Four',
        authorName: 'George Orwell',
        publisherName: 'Signet Classics',
        publicationYear: 1993,
        publicationLocation: 'New York',
        pageCount: 328,
    ));
    app()->instance(ResolvesIsbn::class, $stub);
});

it('returns the resolved values for a valid isbn', function (): void {
    $this->postJson(route('admin.books.isbn'), ['isbn' => '978-0-451-52493-5'])
        ->assertOk()
        ->assertJsonPath('values.name', 'Nineteen Eighty-Four')
        ->assertJsonPath('values.isbn', '9780451524935')
        ->assertJsonPath('writer.suggestion', 'George Orwell');
});

it('rejects an isbn whose check digit is wrong, without asking Open Library', function (string $isbn): void {
    $this->postJson(route('admin.books.isbn'), ['isbn' => $isbn])
        ->assertStatus(422)
        ->assertJsonValidationErrors('isbn');
})->with(['9780451524936', 'not-an-isbn', '', '978045152493']);

it('reports a book that is already in the library', function (): void {
    $existing = Book::factory()->create(['isbn' => '9780451524935', 'name' => 'Already Here']);

    $this->postJson(route('admin.books.isbn'), ['isbn' => '9780451524935'])
        ->assertOk()
        ->assertJsonPath('duplicate.id', $existing->id)
        ->assertJsonPath('duplicate.name', 'Already Here');
});

it('creates a writer from a suggestion', function (): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => 'George Orwell'])
        ->assertOk()
        ->assertJsonPath('name', 'George Orwell');

    expect(Writer::where('name', 'George Orwell')->exists())->toBeTrue();
});

it('reuses an existing writer rather than creating a second', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => 'George Orwell'])
        ->assertOk()
        ->assertJsonPath('id', $writer->id);

    expect(Writer::count())->toBe(1);
});

it('refuses a relation type outside writer and publisher', function (string $type): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => $type, 'name' => 'Anything'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
})->with(['book', 'user', User::class, 'category']);

it('requires a name for the relation', function (): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('turns a guest away from both endpoints', function (string $routeName, array $payload): void {
    auth()->logout();

    $this->postJson(route($routeName), $payload)->assertUnauthorized();
})->with([
    'lookup' => ['admin.books.isbn', ['isbn' => '9780451524935']],
    'relation' => ['admin.books.isbn-relation', ['type' => 'writer', 'name' => 'X']],
]);
