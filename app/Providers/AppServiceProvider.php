<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());

        Model::unguard();

        Model::shouldBeStrict();

        URL::forceHttps(app()->isProduction());

        Vite::useAggressivePrefetching();

        RedirectIfAuthenticated::redirectUsing(fn (): string => route('admin.dashboard'));

        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());

        View::composer('app', function (\Illuminate\View\View $view): void {
            $view->with([
                'accentCss' => AccentRamps::cssVariables(Appearance::accent()),
                'colorScheme' => Appearance::colorScheme(),
            ]);
        });
    }
}
