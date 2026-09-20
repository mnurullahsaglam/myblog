<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Support\Theme\Palette;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    private function sendPasswordResetsThroughOurOwnTemplate(): void
    {
        ResetPassword::toMailUsing(function (mixed $notifiable, string $token): MailMessage {
            $email = $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : '';

            $name = config('app.name');

            return (new MailMessage)
                ->subject('Reset your '.(is_string($name) ? $name : 'panel').' password')
                ->markdown('mail.reset-password', [
                    'url' => url(route('password.reset', ['token' => $token, 'email' => $email], false)),
                    'expiresInMinutes' => $this->passwordResetExpiryInMinutes(),
                    'palette' => Palette::forUser($notifiable instanceof User ? $notifiable : null),
                ]);
        });
    }

    private function passwordResetExpiryInMinutes(): int
    {
        $broker = config('auth.defaults.passwords');
        $expiry = config('auth.passwords.'.(is_string($broker) ? $broker : 'users').'.expire');

        return is_numeric($expiry) ? (int) $expiry : 60;
    }

    public function boot(): void
    {
        Fortify::loginView(fn (): InertiaResponse => Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::twoFactorChallengeView(fn (): InertiaResponse => Inertia::render('Auth/TwoFactorChallenge'));

        Fortify::requestPasswordResetLinkView(fn (): InertiaResponse => Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request): InertiaResponse => Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
        ]));

        Fortify::confirmPasswordView(fn (): InertiaResponse => Inertia::render('Auth/ConfirmPassword'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->sendPasswordResetsThroughOurOwnTemplate();

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(
                Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('api-token', function (Request $request): Limit {
            $throttleKey = Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by('api-token|'.$throttleKey);
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $throttleKey = Str::transliterate(
                Str::lower($request->string(Fortify::email())->toString()).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('invite', fn (Request $request): Limit => Limit::perMinute(6)->by($request->ip() ?? 'unknown'));

        RateLimiter::for('isbn', function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(20)->by(
                'isbn|'.(is_scalar($identifier) ? (string) $identifier : ($request->ip() ?? 'unknown'))
            );
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
