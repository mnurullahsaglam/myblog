<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
