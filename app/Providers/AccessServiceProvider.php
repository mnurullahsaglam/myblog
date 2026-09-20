<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\AccessProfile;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class AccessServiceProvider extends ServiceProvider
{
    /**
     * Scoped rather than singleton: one profile per request, rebuilt for the
     * next one.
     */
    public function register(): void
    {
        $this->app->scoped(AccessProfile::class, function (Application $app): AccessProfile {
            $request = $app->make('request');
            $user = $request->user();

            if (! $user instanceof User) {
                return AccessProfile::forUser(null);
            }

            $stored = $request->hasSession() ? $request->session()->get('preview_role') : null;

            $previewRole = is_string($stored) ? UserRole::tryFrom($stored) : null;

            if (! $previewRole instanceof UserRole) {
                return AccessProfile::forUser($user);
            }

            try {
                return AccessProfile::preview($user, $previewRole);
            } catch (InvalidArgumentException) {
                return AccessProfile::forUser($user);
            }
        });
    }
}
