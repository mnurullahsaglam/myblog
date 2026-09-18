<?php

declare(strict_types=1);

use App\Enums\Currencies;
use App\Forms\Field;
use App\Models\Category;
use App\Models\Client;

it('describes a required text field', function (): void {
    expect(Field::text('title')->required()->schema())->toMatchArray([
        'key' => 'title',
        'type' => 'text',
        'label' => 'Title',
        'required' => true,
        'disabled' => false,
        'columnSpan' => 1,
    ]);
});

it('humanises the label', function (string $key, string $expected): void {
    expect(Field::text($key)->schema()['label'])->toBe($expected);
})->with([
    ['tax_no', 'Tax no'],
    ['client_id', 'Client'],
    ['original_name', 'Original name'],
]);

it('honours an explicit label', function (): void {
    expect(Field::text('tax_no')->label('Tax number')->schema()['label'])->toBe('Tax number');
});

it('carries help text, a placeholder and a default', function (): void {
    $schema = Field::text('color')
        ->help('Hex code, e.g. #FF0000')
        ->placeholderText('#000000')
        ->default('#CCCCCC')
        ->schema();

    expect($schema['help'])->toBe('Hex code, e.g. #FF0000');
    expect($schema['placeholder'])->toBe('#000000');
    expect($schema['default'])->toBe('#CCCCCC');
});

it('builds enum options from HasLabel', function (): void {
    $options = Field::enum('currency', Currencies::class)->schema()['options'];

    expect($options)->toContain(['value' => 'TRY', 'label' => 'Turkish Lira']);
    expect($options)->toHaveCount(count(Currencies::cases()));
});

it('builds select options from a map', function (): void {
    expect(Field::select('status', ['pending' => 'Pending', 'paid' => 'Paid'])->schema()['options'])
        ->toBe([
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'paid', 'label' => 'Paid'],
        ]);
});

it('builds relationship options from the related model', function (): void {
    $acme = Client::factory()->create(['title' => 'Acme']);
    $globex = Client::factory()->create(['title' => 'Globex']);

    expect(Field::relationship('client_id', 'client', 'title')->schema()['options'])
        ->toContain(
            ['value' => $acme->id, 'label' => 'Acme'],
            ['value' => $globex->id, 'label' => 'Globex'],
        );
});

it('builds many-to-many options from the named model', function (): void {
    $rust = Category::factory()->create(['name' => 'Rust']);

    $schema = Field::multiRelationship('categories', Category::class, 'name')->schema();

    expect($schema['type'])->toBe('multiselect');
    expect($schema['options'])->toContain(['value' => $rust->id, 'label' => 'Rust']);
});

it('marks many-to-many fields for syncing', function (): void {
    expect(Field::multiRelationship('categories', Category::class, 'name')->isRelationSync())->toBeTrue();
    expect(Field::relationship('client_id', 'client', 'title')->isRelationSync())->toBeFalse();
    expect(Field::text('title')->isRelationSync())->toBeFalse();
});

it('carries textarea rows in meta', function (): void {
    expect(Field::textarea('description')->rows(5)->schema()['meta'])->toMatchArray(['rows' => 5]);
});

it('carries numeric constraints in meta', function (): void {
    expect(Field::number('amount')->min(0)->max(100)->step(0.01)->schema()['meta'])
        ->toMatchArray(['min' => 0.0, 'max' => 100.0, 'step' => 0.01]);
});

it('carries upload settings in meta', function (): void {
    expect(Field::image('receipt')->directory('receipts')->accept(['image/png'])->schema()['meta'])
        ->toMatchArray(['directory' => 'receipts', 'accept' => ['image/png']]);
});

it('records a slug source for client-side syncing', function (): void {
    expect(Field::text('slug')->slugFrom('title')->schema()['meta'])->toMatchArray(['slugFrom' => 'title']);
});

it('spans two columns when asked', function (): void {
    expect(Field::markdown('content')->columnSpan(2)->schema()['columnSpan'])->toBe(2);
});

it('offers a read-only code field for slugs', function (): void {
    expect(Field::readonlyCode('slug')->schema()['type'])->toBe('readonlyCode');
});

it('returns no options for an unknown relationship', function (): void {
    expect(Field::relationship('unicorn_id', 'unicorn', 'name')->schema()['options'])->toBe([]);
});
