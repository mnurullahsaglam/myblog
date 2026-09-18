<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminNotifier;
use App\Support\Navigation;
use App\Support\Theme\Appearance;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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

            'navigation' => fn (): array => Navigation::clusters(),

            'appearance' => fn (): array => Appearance::toArray(),

            'flash' => [
                'notification' => fn (): mixed => $request->session()->get(AdminNotifier::SESSION_KEY),
            ],

            'env' => [
                'name' => app()->environment(),
                'isProduction' => app()->isProduction(),
            ],
        ];
    }
}
