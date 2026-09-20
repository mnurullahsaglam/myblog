<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ConvertsCurrency;
use App\Contracts\NotifiesAdmin;
use App\Contracts\ResolvesIsbn;
use App\Contracts\SyncsGitHubIssues;
use App\Enums\Area;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\GitHubService;
use App\Services\OpenLibraryService;
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

        // Superseded by access-panel and access-area; kept only until the last
        // caller moves over, so no commit leaves the panel unreachable.
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());

        // denyAsNotFound() is Laravel's own mechanism, so a refusal becomes a
        // genuine 404 rather than a faked exception. A forbidden URL and a
        // nonexistent one are indistinguishable, which is what was asked for.
        Gate::define('access-area', fn (User $user, Area $area): Response => $user->canAccess($area)
            ? Response::allow()
            : Response::denyAsNotFound());

        // The door. Anyone with at least one area belongs in the panel; which
        // screens they reach is decided by the per-area groups inside.
        Gate::define('access-panel', fn (User $user): bool => $user->areas() !== []);

        View::composer('app', function (\Illuminate\View\View $view): void {
            $view->with([
                'accentCss' => AccentRamps::cssVariables(Appearance::accent()),
                'colorScheme' => Appearance::colorScheme(),
            ]);
        });
    }
}
