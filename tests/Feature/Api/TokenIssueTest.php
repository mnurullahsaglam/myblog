<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    RateLimiter::clear('api-token|her@example.test|127.0.0.1');

    $this->member = User::factory()->member()->create([
        'email' => 'her@example.test',
        'password' => Hash::make('correct-horse-battery-staple'),
    ]);
});

function enableTwoFactor(User $user): void
{
    $provider = resolve(TwoFactorAuthenticationProvider::class);

    $user->forceFill([
        'two_factor_secret' => encrypt($provider->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt((string) json_encode(
            Collection::times(8, fn (): string => RecoveryCode::generate())->all(),
        )),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

function currentOtp(User $user): string
{
    $secret = $user->fresh()->two_factor_secret;

    return (new Google2FA)->getCurrentOtp(decrypt(is_string($secret) ? $secret : ''));
}

/** @return array<string, mixed> */
function credentials(array $extra = []): array
{
    return [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Her iPhone',
        ...$extra,
    ];
}

it('issues a token for correct credentials', function (): void {
    $response = $this->postJson(route('api.v1.tokens.store'), credentials())->assertOk();

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($this->member->fresh()->tokens()->count())->toBe(1)
        ->and($this->member->fresh()->tokens()->sole()->name)->toBe('Her iPhone');
});

it('refuses a wrong password and issues nothing', function (): void {
    $this->postJson(route('api.v1.tokens.store'), credentials(['password' => 'wrong']))
        ->assertStatus(422);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

/**
 * The endpoint must not be usable to discover who has an account.
 */
it('refuses an unknown address exactly as it refuses a wrong password', function (): void {
    $known = $this->postJson(route('api.v1.tokens.store'), credentials(['password' => 'wrong']));

    RateLimiter::clear('api-token|nobody@example.test|127.0.0.1');

    $unknown = $this->postJson(route('api.v1.tokens.store'), credentials([
        'email' => 'nobody@example.test',
        'password' => 'wrong',
    ]));

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->json('message'))->toBe($known->json('message'));
});

it('requires a device name', function (): void {
    $payload = credentials();
    unset($payload['device_name']);

    $this->postJson(route('api.v1.tokens.store'), $payload)->assertStatus(422);
});

/**
 * Without this a stolen password is a permanent token and the second factor may
 * as well not be switched on.
 */
it('will not issue a token to a two-factor account without a code', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), credentials())
        ->assertStatus(423)
        ->assertJson(['two_factor' => true]);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

it('issues a token when the code is right', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), credentials(['code' => currentOtp($this->member)]))
        ->assertOk();

    expect($this->member->fresh()->tokens()->count())->toBe(1);
});

it('refuses a wrong code', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), credentials(['code' => '000000']))
        ->assertStatus(423);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

/**
 * Fortify's replaceRecoveryCode() returns void and performs a blind str_replace,
 * so an implementation that treated calling it as validation would accept any
 * string at all. This is the test that catches that.
 */
it('refuses a string that merely looks like a recovery code', function (): void {
    enableTwoFactor($this->member);

    $this->postJson(route('api.v1.tokens.store'), credentials(['code' => 'aaaaaaaaaa-bbbbbbbbbb']))
        ->assertStatus(423);

    expect($this->member->fresh()->tokens()->count())->toBe(0);
});

it('accepts a recovery code once and not twice', function (): void {
    enableTwoFactor($this->member);

    $payload = credentials(['code' => $this->member->fresh()->recoveryCodes()[0]]);

    $this->postJson(route('api.v1.tokens.store'), $payload)->assertOk();
    $this->postJson(route('api.v1.tokens.store'), $payload)->assertStatus(423);

    expect($this->member->fresh()->tokens()->count())->toBe(1);
});

it('throttles attempts', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('api.v1.tokens.store'), credentials(['password' => 'wrong']));
    }

    $this->postJson(route('api.v1.tokens.store'), credentials(['password' => 'wrong']))
        ->assertStatus(429);
});

it('lets a device revoke its own token', function (): void {
    $token = $this->member->createToken('Her iPhone')->plainTextToken;

    $this->withToken($token)->deleteJson(route('api.v1.tokens.destroy'))->assertNoContent();

    expect($this->member->fresh()->tokens()->count())->toBe(0);

    /**
     * The guard caches the user it resolved a moment ago, which production never
     * does because each request is its own process. Forgetting it is what makes
     * the next call a genuine second request.
     */
    $this->app->make('auth')->forgetGuards();

    $this->withToken($token)->getJson(route('api.v1.incomes.index'))->assertUnauthorized();
});
