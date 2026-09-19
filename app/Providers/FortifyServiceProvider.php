<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn (): InertiaResponse => Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::twoFactorChallengeView(fn (): InertiaResponse => Inertia::render('Auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn (): InertiaResponse => Inertia::render('Auth/ConfirmPassword'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(
                Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $loginId = $request->session()->get('login.id');

            return Limit::perMinute(5)->by(is_scalar($loginId) ? (string) $loginId : $request->session()->getId());
        });

        RateLimiter::for('passkeys', function (Request $request): Limit {
            $credentialId = $request->string('credential.id')->toString();

            return Limit::perMinute(10)->by(
                ($credentialId !== '' ? $credentialId : $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
