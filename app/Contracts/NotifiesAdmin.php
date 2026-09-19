<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Transient feedback aimed at the single admin user.
 */
interface NotifiesAdmin
{
    /** Where the flash lands for Inertia to pick up. */
    public const string SESSION_KEY = 'flash.notification';

    public function success(string $title, ?string $body = null): void;

    public function danger(string $title, ?string $body = null): void;

    public function info(string $title, ?string $body = null): void;
}
