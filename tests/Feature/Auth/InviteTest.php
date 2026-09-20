<?php

declare(strict_types=1);

use App\Actions\People\AcceptInvite;
use App\Actions\People\CreateInvite;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

/** @return array{Invite, string} */
function issueInvite(UserRole $role = UserRole::Member, string $email = 'her@example.test'): array
{
    $result = app(CreateInvite::class)->handle($email, $role, test()->owner);

    return [$result['invite'], $result['token']];
}

/** @return array<string, string> */
function acceptPayload(string $name = 'Her Name'): array
{
    return [
        'name' => $name,
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ];
}

it('shows the form for a usable token', function (): void {
    [, $token] = issueInvite();

    $this->get(route('invite.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvite')
            ->where('email', 'her@example.test'));
});

it('creates the account and signs her in', function (): void {
    [$invite, $token] = issueInvite();

    $this->post(route('invite.store', $token), acceptPayload())
        ->assertRedirect(route('admin.profile'));

    $user = User::query()->where('email', 'her@example.test')->sole();

    expect($user->name)->toBe('Her Name')
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invite->fresh()->accepted_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('regenerates the session so the pre-login id cannot be reused', function (): void {
    [, $token] = issueInvite();

    $this->get(route('invite.show', $token));
    $before = session()->getId();

    $this->post(route('invite.store', $token), acceptPayload());

    expect(session()->getId())->not->toBe($before);
});

it('gives her exactly the areas the invite named', function (): void {
    [, $token] = issueInvite();

    $this->post(route('invite.store', $token), acceptPayload());

    $user = User::query()->where('email', 'her@example.test')->sole();

    foreach (Area::cases() as $area) {
        expect($user->canAccess($area))->toBe(
            in_array($area, UserRole::Member->areas(), true),
            "{$area->value} did not match the invited role",
        );
    }
});

it('creates an admin when the invite says so', function (): void {
    [, $token] = issueInvite(UserRole::Admin, 'them@example.test');

    $this->post(route('invite.store', $token), acceptPayload('Them'));

    expect(User::query()->where('email', 'them@example.test')->sole()->role)
        ->toBe(UserRole::Admin);
});

it('ignores an email submitted with the form', function (): void {
    [, $token] = issueInvite();

    $this->post(route('invite.store', $token), [
        ...acceptPayload(),
        'email' => 'someone-else@example.test',
    ]);

    expect(User::query()->where('email', 'someone-else@example.test')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'her@example.test')->exists())->toBeTrue();
});

it('refuses every kind of unusable token', function (string $case): void {
    [$invite, $token] = issueInvite();

    $token = match ($case) {
        'expired' => tap($token, fn () => $invite->update(['expires_at' => now()->subMinute()])),
        'revoked' => tap($token, fn () => $invite->update(['revoked_at' => now()])),
        'accepted' => tap($token, fn () => $invite->update(['accepted_at' => now()])),
        'tampered' => mb_substr($token, 0, 63).($token[63] === 'a' ? 'b' : 'a'),
        'unknown' => str_repeat('z', 64),
    };

    $this->get(route('invite.show', $token))->assertNotFound();
    $this->post(route('invite.store', $token), acceptPayload())->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
})->with(['expired', 'revoked', 'accepted', 'tampered', 'unknown']);

it('cannot be spent twice', function (): void {
    [, $token] = issueInvite();

    $this->post(route('invite.store', $token), acceptPayload());

    auth()->logout();

    $this->post(route('invite.store', $token), acceptPayload())->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->count())->toBe(1);
});

/**
 * The concurrency case, as close as a single test process can get to it.
 *
 * Two simultaneous accepts are prevented by the row lock inside AcceptInvite,
 * and a lock cannot be observed from here. What can be observed is the re-check
 * the lock exists to protect: an invite that was spent after this request
 * loaded it must not produce a second account.
 */
it('refuses an invite that was spent between loading and accepting', function (): void {
    [$invite] = issueInvite();

    $loaded = Invite::query()->findOrFail($invite->getKey());

    Invite::query()->whereKey($invite->getKey())->update(['accepted_at' => now()]);

    expect(fn (): User => app(AcceptInvite::class)
        ->handle($loaded, 'Her Name', 'correct-horse-battery-staple'))
        ->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
});

it('refuses a token whose address gained an account in the meantime', function (): void {
    [, $token] = issueInvite();

    User::factory()->create(['email' => 'her@example.test']);

    $this->post(route('invite.store', $token), acceptPayload())->assertNotFound();

    expect(User::query()->where('email', 'her@example.test')->count())->toBe(1);
});

it('sends a signed-in visitor to the dashboard instead of the form', function (): void {
    [, $token] = issueInvite();

    $this->actingAs($this->owner)
        ->get(route('invite.show', $token))
        ->assertRedirect(route('admin.dashboard'));
});

it('requires a name and a confirmed password', function (array $payload, string $field): void {
    [, $token] = issueInvite();

    $this->from(route('invite.show', $token))
        ->post(route('invite.store', $token), $payload)
        ->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'her@example.test')->exists())->toBeFalse();
})->with([
    'no name' => [['password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple'], 'name'],
    'no password' => [['name' => 'Her'], 'password'],
    'unconfirmed' => [['name' => 'Her', 'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'different'], 'password'],
    'too short' => [['name' => 'Her', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);
