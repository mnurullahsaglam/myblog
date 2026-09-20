<?php

declare(strict_types=1);

use App\Support\Isbn;

it('accepts a well formed ISBN and normalises it to thirteen digits', function (string $input, string $expected): void {
    expect(Isbn::tryFrom($input)?->value())->toBe($expected);
})->with([
    'plain isbn-13' => ['9780451524935', '9780451524935'],
    'hyphenated isbn-13' => ['978-0-451-52493-5', '9780451524935'],
    'spaced isbn-13' => ['978 0 451 52493 5', '9780451524935'],
    'isbn-10 converts to 13' => ['0451524934', '9780451524935'],
    'hyphenated isbn-10' => ['0-451-52493-4', '9780451524935'],
    'isbn-10 with an X check digit' => ['043942089X', '9780439420891'],
    'surrounding whitespace' => ['  9780451524935  ', '9780451524935'],
]);

it('rejects anything that is not a valid ISBN', function (string $input): void {
    expect(Isbn::tryFrom($input))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    'too short' => ['978045152493'],
    'too long' => ['97804515249355'],
    'letters' => ['not-an-isbn-at'],
    'bad isbn-13 check digit' => ['9780451524936'],
    'bad isbn-10 check digit' => ['0451524935'],
    'transposed digits break the checksum' => ['9780451524953'],
    'X in the wrong position' => ['04X9420891'],
    'thirteen digits with a bad prefix' => ['1230451524935'],
]);

it('cannot detect a transposition of digits differing by five, as the format allows', function (): void {
    expect(Isbn::tryFrom('9780451529435'))->not->toBeNull();
});

it('treats the two forms of the same book as equal', function (): void {
    expect(Isbn::tryFrom('0451524934')?->value())->toBe(Isbn::tryFrom('9780451524935')?->value());
});
