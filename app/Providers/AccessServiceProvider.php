<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\AccessProfile;
use App\Support\Features;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

final class AccessServiceProvider extends ServiceProvider
{
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

    public function boot(): void
    {
        foreach (Features::ALL as $flag) {
            Feature::define($flag, fn (): bool => false);
        }
    }
}
