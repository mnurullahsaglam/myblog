<?php

declare(strict_types=1);

namespace App\Contracts;

interface NotifiesAdmin
{
    public const string SESSION_KEY = 'flash.notification';

    public function success(string $title, ?string $body = null): void;

    public function danger(string $title, ?string $body = null): void;

    public function info(string $title, ?string $body = null): void;
}
