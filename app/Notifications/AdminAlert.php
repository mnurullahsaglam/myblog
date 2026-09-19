<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A persisted alert for the admin, surfaced by the notification bell.
 */
final class AdminAlert extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly ?string $body = null,
        private readonly string $variant = 'info',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string|null, variant: string}
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'variant' => $this->variant,
        ];
    }
}
