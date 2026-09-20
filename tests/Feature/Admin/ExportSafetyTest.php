<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function nameCell(string $csv): string
{
    $lines = array_values(array_filter(explode("\n", trim($csv))));
    $headings = str_getcsv($lines[0], escape: '\\');
    $row = str_getcsv($lines[1], escape: '\\');

    $index = array_search('name', $headings, true);

    return is_int($index) ? (string) $row[$index] : '';
}

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

it('does not let a title become a spreadsheet formula', function (string $title): void {
    Book::factory()->create(['name' => $title]);

    $csv = Storage::disk('local')->get(resolve(ExportResource::class)->handle('books'));

    expect(nameCell($csv))->toBe("'".$title);
})->with([
    'formula' => ['=1+1'],
    'hyperlink' => ['=HYPERLINK("http://evil.test","click")'],
    'plus' => ['+1'],
    'minus' => ['-1'],
    'at' => ['@SUM(A1)'],
]);

it('leaves an ordinary title alone', function (): void {
    Book::factory()->create(['name' => 'Ordinary Title']);

    $csv = Storage::disk('local')->get(resolve(ExportResource::class)->handle('books'));

    expect(nameCell($csv))->toBe('Ordinary Title');
});

it('expires a download link', function (): void {
    $this->actingAs($this->owner)->post(route('admin.exports.store', 'books'))->assertRedirect();

    $url = session('flash.notification')['body'] ?? '';

    expect($url)->toContain('expires=');

    $this->travel(2)->hours();

    $this->actingAs($this->owner)->get($url)->assertForbidden();
});

it('serves the download while the link is fresh', function (): void {
    $this->actingAs($this->owner)->post(route('admin.exports.store', 'books'))->assertRedirect();

    $url = session('flash.notification')['body'] ?? '';

    $this->actingAs($this->owner)->get($url)->assertOk();
});
