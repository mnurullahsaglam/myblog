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
     * Bound rather than scoped, deliberately.
     *
     * A scoped instance survives for the life of the container, which is one
     * request under FPM but many requests inside a single test. That made the
     * access matrix reuse the owner's profile for the member's half of each
     * case, so every route looked reachable and the suite passed without
     * testing anything. Correctness in the test that guards this feature is
     * worth rebuilding a handful of enum arrays per resolution.
     */
    public function register(): void
    {
        $this->app->bind(AccessProfile::class, function (Application $app): AccessProfile {
            $request = $app->make('request');

            $user = $app->make('auth')->guard()->user();

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
