<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
});

/**
 * Managing passkeys sits behind Fortify's password confirmation, so these tests
 * mark the password as freshly confirmed.
 */
function withConfirmedPassword(): TestCase
{
    return test()->withSession(['auth.password_confirmed_at' => time()]);
}

it('makes the user a passkey user', function (): void {
    expect($this->admin)->toBeInstanceOf(PasskeyUser::class)
        ->and($this->admin->passkeys())->toBeInstanceOf(HasMany::class)
        ->and($this->admin->hasPasskeysEnabled())->toBeFalse();
});

it('derives a stable user handle that is not the id', function (): void {
    $handle = $this->admin->getPasskeyUserHandle();

    expect($handle)->toBe($this->admin->getPasskeyUserHandle())->not->toContain((string) $this->admin->id);
});

it('gives authenticators a display name and username', function (): void {
    expect($this->admin->getPasskeyDisplayName())->toBe($this->admin->name)
        ->and($this->admin->getPasskeyUsername())->toBe('admin@example.test');
});

it('registers the passkey routes', function (string $name): void {
    expect(Route::has($name))->toBeTrue();
})->with([
    'passkey.login',
    'passkey.login-options',
    'passkey.store',
    'passkey.destroy',
    'passkey.registration-options',
]);

it('offers registration options to an authenticated user', function (): void {
    $response = withConfirmedPassword()
        ->actingAs($this->admin)
        ->getJson(route('passkey.registration-options'))
        ->assertOk();

    $options = $response->json('options');

    expect($options)->toHaveKeys(['challenge', 'rp', 'user', 'pubKeyCredParams']);
    // Derived from APP_URL, which differs between local and CI.
    expect($options['rp']['id'])->toBe(parse_url((string) config('app.url'), PHP_URL_HOST));
    expect($options['user']['displayName'])->toBe($this->admin->name)
        ->and(session()->has('passkey.registration_options'))->toBeTrue();
});

it('refuses registration options to a guest', function (): void {
    $this->getJson(route('passkey.registration-options'))->assertUnauthorized();
});

it('offers login options to a guest', function (): void {
    $response = $this->getJson(route('passkey.login-options'))->assertOk();

    expect($response->json('options'))->toHaveKey('challenge')
        ->and(session()->has('passkey.verification_options'))->toBeTrue();
});

it('rejects a malformed credential', function (): void {
    withConfirmedPassword()
        ->actingAs($this->admin)
        ->postJson(route('passkey.store'), ['name' => 'Laptop', 'credential' => ['nope' => true]])
        ->assertStatus(422);
});

it('requires a name when registering', function (): void {
    withConfirmedPassword()
        ->actingAs($this->admin)
        ->postJson(route('passkey.store'), ['credential' => ['id' => 'x', 'rawId' => 'x', 'type' => 'public-key', 'response' => []]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('demands a confirmed password before touching passkeys', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('passkey.registration-options'))
        ->assertStatus(423);
});

it('lists passkeys on the profile page', function (): void {
    Passkey::query()->create([
        'user_id' => $this->admin->id,
        'name' => 'MacBook Touch ID',
        'credential_id' => 'abc123',
        'credential' => '{}',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.profile'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('passkeys', 1)
            ->where('passkeys.0.name', 'MacBook Touch ID')
            ->where('passkeys.0.lastUsedAt', null)
        );
});

it('never leaks the stored credential to the browser', function (): void {
    Passkey::query()->create([
        'user_id' => $this->admin->id,
        'name' => 'MacBook Touch ID',
        'credential_id' => 'secret-credential-id',
        'credential' => '{"secret":"material"}',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.profile'))
        ->assertDontSee('secret-credential-id')
        ->assertDontSee('material');
});

it('deletes only your own passkey', function (): void {
    $other = User::factory()->create(['email' => 'other@example.test']);

    $mine = Passkey::query()->create([
        'user_id' => $this->admin->id,
        'name' => 'Mine',
        'credential_id' => 'mine',
        'credential' => '{}',
    ]);

    $theirs = Passkey::query()->create([
        'user_id' => $other->id,
        'name' => 'Theirs',
        'credential_id' => 'theirs',
        'credential' => '{}',
    ]);

    withConfirmedPassword()
        ->actingAs($this->admin)
        ->delete(route('passkey.destroy', $theirs))
        ->assertForbidden();
    expect(Passkey::find($theirs->id))->not->toBeNull();

    withConfirmedPassword()
        ->actingAs($this->admin)
        ->delete(route('passkey.destroy', $mine));
    expect(Passkey::find($mine->id))->toBeNull();
});

it('sends a passkey login to the panel', function (): void {
    expect(config('passkeys.redirect'))->toBe('/admin');
});

it('binds passkeys to the application origin', function (): void {
    expect(config('passkeys.relying_party_id'))->toBe(parse_url((string) config('app.url'), PHP_URL_HOST))
        ->and(config('passkeys.allowed_origins'))->toContain(config('app.url'));
});
