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

        Model::shouldBeStrict(! app()->isProduction());

        URL::forceHttps(app()->isProduction());

        Vite::useAggressivePrefetching();

        RedirectIfAuthenticated::redirectUsing(fn (): string => route('admin.dashboard'));

        Gate::define('access-area', function (User $user, Area|string $area): Response {
            $area = $area instanceof Area ? $area : Area::tryFrom($area);

            return $area instanceof Area && resolve(AccessProfile::class)->canAccess($area)
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        Gate::define('access-panel', fn (User $user): bool => resolve(AccessProfile::class)->areas() !== []);

        Gate::define('has-ability', fn (User $user, Ability $ability): bool => resolve(AccessProfile::class)->allows($ability));

        View::composer('app', function (\Illuminate\View\View $view): void {
            $appearance = Appearance::forUser(auth()->user());

            $view->with([
                'accentCss' => AccentRamps::cssVariables($appearance['accent']),
                'colorScheme' => $appearance['colorScheme'],
            ]);
        });
    }
}
