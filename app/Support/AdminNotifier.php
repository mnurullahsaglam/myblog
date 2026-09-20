<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\NotifiesAdmin;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Log;

final readonly class AdminNotifier implements NotifiesAdmin
{
    public function __construct(private bool $forceLog = false) {}

    public function success(string $title, ?string $body = null): void
    {
        $this->send('success', $title, $body);
    }

    public function danger(string $title, ?string $body = null): void
    {
        $this->send('danger', $title, $body);
    }

    public function info(string $title, ?string $body = null): void
    {
        $this->send('info', $title, $body);
    }

    private function send(string $variant, string $title, ?string $body): void
    {
        $session = $this->session();

        if (! $session instanceof Store) {
            Log::info($title, ['body' => $body]);

            return;
        }

        $session->flash(self::SESSION_KEY, [
            'variant' => $variant,
            'title' => $title,
            'body' => $body,
        ]);
    }

    private function session(): ?Store
    {
        if ($this->forceLog || ! app()->bound('session')) {
            return null;
        }

        $store = resolve('session')->driver();

        return $store instanceof Store && $store->isStarted() ? $store : null;
    }
}
