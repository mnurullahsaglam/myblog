<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Session\Store;
use Illuminate\Support\Facades\Log;

/**
 * Transient admin feedback.
 *
 * During an HTTP request this flashes into the session, where Inertia picks it
 * up as `flash.notification` and the layout renders a toast. Outside a request
 * - scheduled commands, queued jobs - there is nobody to show a toast to, so it
 * falls back to the log. Persisted alerts that must survive the request use
 * {@see \App\Notifications\AdminAlert} instead.
 */
class AdminNotifier
{
    public const SESSION_KEY = 'flash.notification';

    public function __construct(private readonly bool $forceLog = false) {}

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

    /**
     * The active session, or null when there is no request to flash into.
     */
    private function session(): ?Store
    {
        if ($this->forceLog || ! app()->bound('session')) {
            return null;
        }

        $store = resolve('session')->driver();

        return $store instanceof Store && $store->isStarted() ? $store : null;
    }
}
