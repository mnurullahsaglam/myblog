<?php

declare(strict_types=1);

/**
 * The suite must never reach a real mail server, whatever a developer's .env
 * says. phpunit.xml pins this; the test is what notices if that is edited.
 */
it('sends mail nowhere during tests', function (): void {
    expect(config('mail.default'))->toBe('array');
});
