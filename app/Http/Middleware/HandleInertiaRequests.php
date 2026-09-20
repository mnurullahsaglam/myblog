<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\NotifiesAdmin;
use App\Support\Access\AccessProfile;
use App\Support\Navigation;
use App\Support\Theme\Appearance;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;
use Override;

final class HandleInertiaRequests extends Middleware
{
    #[Override]
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],

            'navigation' => fn (): array => Navigation::forProfile(app(AccessProfile::class)),

            'appearance' => fn (): array => Appearance::forUser($user),

            'flash' => [
                'notification' => fn (): mixed => $request->session()->get(NotifiesAdmin::SESSION_KEY),
            ],

            'notifications' => fn (): array => $user === null
                ? ['unreadCount' => 0, 'items' => []]
                : [
                    'unreadCount' => $user->unreadNotifications()->count(),
                    'items' => $user->notifications()
                        ->latest()
                        ->limit(15)
                        ->get()
                        ->map(fn (DatabaseNotification $notification): array => [
                            'id' => $notification->id,
                            'title' => is_string($notification->data['title'] ?? null)
                                ? $notification->data['title']
                                : 'Notification',
                            'body' => is_string($notification->data['body'] ?? null)
                                ? $notification->data['body']
                                : null,
                            'variant' => is_string($notification->data['variant'] ?? null)
                                ? $notification->data['variant']
                                : 'info',
                            'createdAt' => $notification->created_at?->diffForHumans(),
                            'readAt' => $notification->read_at?->toIso8601String(),
                        ])
                        ->all(),
                ],

            'env' => [
                'name' => app()->environment(),
                'isProduction' => app()->isProduction(),
            ],
        ];
    }
}
