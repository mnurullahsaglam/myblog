<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        // Scoped to the user's own notifications, so one account cannot mark
        // another's as read.
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

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()?->notifications()->whereKey($notification)->first();

        abort_if($record === null, 404);

        $record->delete();

        return back();
    }
}
