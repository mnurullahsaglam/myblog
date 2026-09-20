<?php

declare(strict_types=1);

it('sends mail nowhere during tests', function (): void {
    expect(config('mail.default'))->toBe('array');
});
