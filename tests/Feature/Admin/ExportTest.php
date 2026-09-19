<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('local');
});

it('rejects an unknown resource', function (): void {
    $this->post(route('admin.exports.store', 'unicorns'))->assertNotFound();
});

it('writes a csv with the expected headings and rows', function (): void {
    $writer = Writer::factory()->create(['name' => 'Le Guin']);
    $publisher = Publisher::factory()->create(['name' => 'Ace']);
    $book = Book::factory()->create([
        'name' => 'The Dispossessed',
        'writer_id' => $writer->id,
        'publisher_id' => $publisher->id,
    ]);
    $book->categories()->attach(Category::factory()->create(['name' => 'Utopian']));

    $path = resolve(ExportResource::class)->handle('books');

    Storage::disk('local')->assertExists($path);

    $lines = array_values(array_filter(explode("\n", Storage::disk('local')->get($path))));
    expect($lines)->sequence(fn ($e) => $e->toContain('name', 'writer', 'publisher', 'categories'), fn ($e) => $e->toContain('The Dispossessed', 'Le Guin', 'Ace', 'Utopian'));
});

it('exports every row, not just the first page', function (): void {
    Publisher::factory()->count(120)->create();

    $path = resolve(ExportResource::class)->handle('publishers');

    $lines = array_values(array_filter(explode("\n", Storage::disk('local')->get($path))));

    expect($lines)->toHaveCount(121);
});

it('includes counts in the writers export', function (): void {
    $writer = Writer::factory()->create(['name' => 'Prolific']);
    Book::factory()->count(3)->create(['writer_id' => $writer->id]);

    $path = resolve(ExportResource::class)->handle('writers');
    $csv = Storage::disk('local')->get($path);

    expect($csv)->toContain('Prolific')
        ->and(str_contains($csv, ',3'))->toBeTrue();
});

it('notifies with a signed download link when the export finishes', function (): void {
    Writer::factory()->count(2)->create();

    $this->post(route('admin.exports.store', 'writers'))->assertRedirect();

    $notification = session('flash.notification');

    expect($notification)->toHaveKey('variant', 'success')
        ->and($notification['body'])->toContain('signature=');
});

it('refuses an unsigned download', function (): void {
    $this->get('/admin/exports/download?path=exports/anything.csv')->assertForbidden();
});

it('serves a signed download', function (): void {
    Storage::disk('local')->put('exports/test.csv', "a,b\n1,2\n");

    $this->get(URL::signedRoute('admin.exports.download', ['path' => 'exports/test.csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('refuses a signed url pointing outside the exports directory', function (): void {
    Storage::disk('local')->put('invoices/secret.zip', 'nope');

    $this->get(URL::signedRoute('admin.exports.download', ['path' => 'invoices/secret.zip']))
        ->assertForbidden();
});

it('refuses a signed url that tries to climb out', function (): void {
    $this->get(URL::signedRoute('admin.exports.download', ['path' => 'exports/../invoices/secret.zip']))
        ->assertForbidden();
});

it('returns 404 for a signed url to a missing export', function (): void {
    $this->get(URL::signedRoute('admin.exports.download', ['path' => 'exports/gone.csv']))
        ->assertNotFound();
});

it('forbids a non-admin from exporting', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'nobody@example.test']));

    $this->post(route('admin.exports.store', 'writers'))->assertForbidden();
});
