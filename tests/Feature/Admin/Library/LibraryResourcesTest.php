<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\User;
use App\Models\Writer;
use App\Support\Navigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('lists publishers with their book counts', function (): void {
    $publisher = Publisher::factory()->create(['name' => 'Ace']);
    Book::factory()->count(4)->create(['publisher_id' => $publisher->id]);

    $this->get(route('admin.publishers.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Library/Publishers/Index')
            ->where('rows.data.0.cells.name.display', 'Ace')
            ->where('rows.data.0.cells.books_count.display', '4')
        );
});

it('creates a publisher', function (): void {
    $this->post(route('admin.publishers.store'), ['name' => 'New Press', 'slug' => 'new-press'])
        ->assertRedirect(route('admin.publishers.index'));

    $this->assertDatabaseHas('publishers', ['name' => 'New Press']);
});

it('requires a publisher name of at least three characters', function (): void {
    $this->from(route('admin.publishers.create'))
        ->post(route('admin.publishers.store'), ['name' => 'ab', 'slug' => 'ab'])
        ->assertSessionHasErrors('name');
});

it('lists writers and renders years without separators', function (): void {
    Writer::factory()->create(['name' => 'Le Guin', 'birth_year' => 1929, 'death_year' => 2018]);

    $this->get(route('admin.writers.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Library/Writers/Index')
            ->where('rows.data.0.cells.birth_year.display', '1929')
            ->where('rows.data.0.cells.death_year.display', '2018')
        );
});

it('shows a dash for a living writer', function (): void {
    Writer::factory()->create(['death_year' => null]);

    $this->get(route('admin.writers.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.cells.death_year.display', '—')
        );
});

it('filters writers by living or deceased', function (): void {
    Writer::factory()->create(['death_year' => 2018]);
    Writer::factory()->create(['death_year' => null]);

    $this->get(route('admin.writers.index', ['filter' => ['death_year' => 'yes']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));

    $this->get(route('admin.writers.index', ['filter' => ['death_year' => 'no']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('creates a writer with a portrait', function (): void {
    $this->post(route('admin.writers.store'), [
        'name' => 'Ursula Le Guin',
        'slug' => 'ursula-le-guin',
        'birth_year' => 1929,
        'death_year' => 2018,
        'birth_place' => 'Berkeley',
        'image' => UploadedFile::fake()->image('portrait.jpg'),
    ])->assertRedirect(route('admin.writers.index'));

    $writer = Writer::where('slug', 'ursula-le-guin')->firstOrFail();

    expect($writer->image)->toStartWith('writers/');
    Storage::disk('public')->assertExists($writer->image);
});

it('rejects a writer who died before being born', function (): void {
    $this->from(route('admin.writers.create'))
        ->post(route('admin.writers.store'), [
            'name' => 'Impossible',
            'slug' => 'impossible',
            'birth_year' => 2000,
            'death_year' => 1990,
        ])
        ->assertSessionHasErrors('death_year');
});

it('rejects a birth year in the future', function (): void {
    $this->from(route('admin.writers.create'))
        ->post(route('admin.writers.store'), [
            'name' => 'Time Traveller',
            'slug' => 'time-traveller',
            'birth_year' => (int) date('Y') + 5,
        ])
        ->assertSessionHasErrors('birth_year');
});

it('keeps the stored portrait when no new file is uploaded', function (): void {
    $writer = Writer::factory()->create(['image' => 'writers/existing.jpg']);

    $this->put(route('admin.writers.update', $writer), ['name' => 'Renamed', 'slug' => $writer->slug]);

    expect($writer->fresh()->image)->toBe('writers/existing.jpg');
});

it('lists books with writer and publisher names', function (): void {
    $writer = Writer::factory()->create(['name' => 'Le Guin']);
    $publisher = Publisher::factory()->create(['name' => 'Ace']);
    Book::factory()->create([
        'name' => 'The Dispossessed',
        'writer_id' => $writer->id,
        'publisher_id' => $publisher->id,
        'publication_date' => 1974,
    ]);

    $this->get(route('admin.books.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $page->component('Library/Books/Index');

            $cells = $page->toArray()['props']['rows']['data'][0]['cells'];

            expect($cells['name']['display'])->toBe('The Dispossessed')
                ->and($cells['writer.name']['display'])->toBe('Le Guin')
                ->and($cells['publisher.name']['display'])->toBe('Ace')
                ->and($cells['publication_date']['display'])->toBe('1974');
        });
});

it('searches books by writer name', function (): void {
    $leGuin = Writer::factory()->create(['name' => 'Le Guin']);
    $other = Writer::factory()->create(['name' => 'Someone Else']);
    Book::factory()->create(['writer_id' => $leGuin->id]);
    Book::factory()->create(['writer_id' => $other->id]);

    $this->get(route('admin.books.index', ['search' => 'Le Guin']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('filters books by writer', function (): void {
    $leGuin = Writer::factory()->create();
    Book::factory()->create(['writer_id' => $leGuin->id]);
    Book::factory()->create();

    $this->get(route('admin.books.index', ['filter' => ['writer_id' => [$leGuin->id]]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows.data', 1));
});

it('creates a book with categories', function (): void {
    $writer = Writer::factory()->create();
    $publisher = Publisher::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $this->post(route('admin.books.store'), [
        'writer_id' => $writer->id,
        'publisher_id' => $publisher->id,
        'name' => 'A New Book',
        'slug' => 'a-new-book',
        'page_count' => 320,
        'publication_date' => 1974,
        'categories' => $categories->pluck('id')->all(),
    ])->assertRedirect(route('admin.books.index'));

    $book = Book::where('slug', 'a-new-book')->firstOrFail();

    expect($book->page_count)->toBe(320)
        ->and($book->categories)->toHaveCount(2);
});

it('requires a writer and a publisher', function (): void {
    $this->from(route('admin.books.create'))
        ->post(route('admin.books.store'), ['name' => 'Orphan Book', 'slug' => 'orphan'])
        ->assertSessionHasErrors(['writer_id', 'publisher_id']);
});

it('rejects a writer that does not exist', function (): void {
    $publisher = Publisher::factory()->create();

    $this->from(route('admin.books.create'))
        ->post(route('admin.books.store'), [
            'name' => 'Ghost Book',
            'slug' => 'ghost',
            'writer_id' => 999999,
            'publisher_id' => $publisher->id,
        ])
        ->assertSessionHasErrors('writer_id');
});

it('does not clash with the public books routes', function (): void {
    expect(route('admin.books.index'))->toContain('/admin/books')
        ->and(route('books.index'))->not->toContain('/admin/');
});

it('keeps the public book pages working', function (): void {
    auth()->logout();

    $this->get('/books')->assertOk();
});

it('lists the library cluster in navigation', function (): void {
    $library = collect(Navigation::clusters())->firstWhere('label', 'Library');

    expect(collect($library['items'])->pluck('route')->all())
        ->toContain('admin.books.index', 'admin.writers.index', 'admin.publishers.index');
});
