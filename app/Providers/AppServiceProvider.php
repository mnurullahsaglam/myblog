<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ConvertsCurrency;
use App\Contracts\NotifiesAdmin;
use App\Contracts\SyncsGitHubIssues;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\GitHubService;
use App\Support\AdminNotifier;
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

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConvertsCurrency::class, ExchangeRateService::class);
        $this->app->bind(SyncsGitHubIssues::class, GitHubService::class);
        $this->app->bind(NotifiesAdmin::class, AdminNotifier::class);
    }

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
