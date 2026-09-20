<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Notifications\DismissNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class NotificationController extends Controller
{
    /**
     * Notifications are keyed by UUID, and PostgreSQL rejects a malformed value
     * with a QueryException rather than matching nothing. Checking the shape
     * first keeps a bad id a 404 rather than a 500.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        abort_if(! Str::isUuid($notification), 404);

        $record = $request->user()?->notifications()->whereKey($notification)->first();

        abort_if($record === null, 404);

        $record->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back();
    }

    public function destroy(Request $request, string $notification, DismissNotification $dismiss): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $dismiss->handle($user, $notification);

        return back();
    }
}
