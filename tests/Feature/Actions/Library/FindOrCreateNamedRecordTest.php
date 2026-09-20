<?php

declare(strict_types=1);

use App\Actions\Library\FindOrCreateNamedRecord;
use App\Models\Publisher;
use App\Models\Writer;

it('returns the existing record when the name already exists', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    $found = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'George Orwell');

    expect($found->getKey())->toBe($writer->getKey())
        ->and(Writer::count())->toBe(1);
});

it('matches regardless of case', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'george orwell')->getKey())
        ->toBe($writer->getKey());
});

it('creates the record when no name matches', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Ursula K. Le Guin');

    expect($created->exists)->toBeTrue()
        ->and($created->getAttribute('name'))->toBe('Ursula K. Le Guin');
});

it('lets the model generate its own slug', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Ursula K. Le Guin');

    expect($created->getAttribute('slug'))->toBe('ursula-k-le-guin');
});

it('works for publishers too', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Publisher::class, 'Signet Classics');

    expect($created)->toBeInstanceOf(Publisher::class)
        ->and(Publisher::count())->toBe(1);
});

it('takes the lowest id when two records share a name', function (): void {
    $first = Writer::factory()->create(['name' => 'Duplicate Name']);
    Writer::factory()->create(['name' => 'Duplicate Name']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Duplicate Name')->getKey())
        ->toBe($first->getKey());
});

it('trims surrounding whitespace before matching', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, '  George Orwell  ')->getKey())
        ->toBe($writer->getKey());
});
