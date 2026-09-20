<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ConvertsCurrency;
use App\Contracts\NotifiesAdmin;
use App\Contracts\ResolvesIsbn;
use App\Contracts\SyncsGitHubIssues;
use App\Enums\Ability;
use App\Enums\Area;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\GitHubService;
use App\Services\OpenLibraryService;
use App\Support\Access\AccessProfile;
use App\Support\AdminNotifier;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConvertsCurrency::class, ExchangeRateService::class);
        $this->app->bind(SyncsGitHubIssues::class, GitHubService::class);
        $this->app->bind(NotifiesAdmin::class, AdminNotifier::class);
        $this->app->bind(ResolvesIsbn::class, OpenLibraryService::class);
    }

    public function boot(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());

        Model::unguard();

        // Strict mode turns a lazy load into an exception, which is a useful
        // nudge in development and a 500 for the visitor in production.
        Model::shouldBeStrict(! app()->isProduction());

        URL::forceHttps(app()->isProduction());

        Vite::useAggressivePrefetching();

        RedirectIfAuthenticated::redirectUsing(fn (): string => route('admin.dashboard'));

        // denyAsNotFound() is Laravel's own mechanism, so a refusal becomes a
        // genuine 404 rather than a faked exception. A forbidden URL and a
        // nonexistent one are indistinguishable, which is what was asked for.
        /**
         * Every access question is answered by the request's AccessProfile
         * rather than by the user these closures are handed, so that view-as
         * substitutes all of them at once. The route middleware passes the area
         * as a string while callers in PHP pass the enum, so both are accepted,
         * and an area the enum does not know denies rather than throws.
         */
        Gate::define('access-area', function (User $user, Area|string $area): Response {
            $area = $area instanceof Area ? $area : Area::tryFrom($area);

            return $area instanceof Area && app(AccessProfile::class)->canAccess($area)
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        Gate::define('access-panel', fn (User $user): bool => app(AccessProfile::class)->areas() !== []);

        Gate::define('has-ability', fn (User $user, Ability $ability): bool => app(AccessProfile::class)->allows($ability));

        View::composer('app', function (\Illuminate\View\View $view): void {
            $view->with([
                'accentCss' => AccentRamps::cssVariables(Appearance::accent()),
                'colorScheme' => Appearance::colorScheme(),
            ]);
        });
    }
}
