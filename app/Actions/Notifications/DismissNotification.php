<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Delete one of a user's own notifications.
 *
 * Scoped through the relation rather than looked up by id, so one account
 * cannot dismiss another's. An unknown id and someone else's id are
 * indistinguishable from outside, which is the point.
 */
final class DismissNotification
{
    public function handle(User $user, string $notificationId): void
    {
        $record = $user->notifications()->whereKey($notificationId)->first();

        throw_if($record === null, NotFoundHttpException::class);

        $record->delete();
    }
}
