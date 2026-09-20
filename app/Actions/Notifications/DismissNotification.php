<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Delete one of a user's own notifications.
 *
 * Scoped through the relation rather than looked up by id, so one account
 * cannot dismiss another's. An unknown id and someone else's id are
 * indistinguishable from outside, which is the point.
 *
 * The shape of the id is checked before it reaches the query because
 * notifications are keyed by UUID: PostgreSQL rejects a malformed value with a
 * QueryException rather than matching nothing, which turned a 404 into a 500.
 * MySQL and SQLite compare it as text and never noticed.
 */
final class DismissNotification
{
    public function handle(User $user, string $notificationId): void
    {
        throw_if(! Str::isUuid($notificationId), NotFoundHttpException::class);

        $record = $user->notifications()->whereKey($notificationId)->first();

        throw_if($record === null, NotFoundHttpException::class);

        $record->delete();
    }
}
