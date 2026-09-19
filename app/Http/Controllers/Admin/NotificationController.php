<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Notifications\DismissNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
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
