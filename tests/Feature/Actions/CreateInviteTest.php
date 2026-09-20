<?php

declare(strict_types=1);

use App\Actions\People\CreateInvite;
use App\Enums\UserRole;
use App\Mail\InviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

it('issues an invite with a token that is not what it stores', function (): void {
    ['invite' => $invite, 'token' => $token] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    expect($token)->toHaveLength(64)
        ->and($invite->token_hash)->toBe(hash('sha256', $token))
        ->and($invite->token_hash)->not->toBe($token);
});

it('sets the window to 48 hours', function (): void {
    $this->freezeTime();

    ['invite' => $invite] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    // To the second: the column stores whole seconds, and now() carries
    // microseconds that never survive the round trip.
    expect($invite->expires_at->toDateTimeString())->toBe(now()->addHours(48)->toDateTimeString());
});

it('records who sent it and what it grants', function (): void {
    ['invite' => $invite] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Admin, $this->owner);

    expect($invite->role)->toBe(UserRole::Admin)
        ->and($invite->invited_by)->toBe($this->owner->id);
});

it('sends exactly one mail, to the invited address', function (): void {
    resolve(CreateInvite::class)->handle('her@example.test', UserRole::Member, $this->owner);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(InviteMail::class, fn (InviteMail $mail): bool => $mail->hasTo('her@example.test'));
});

/**
 * The mail must carry the usable secret and the database must not. If these two
 * ever hold the same string, the hashing has been undone by a refactor.
 */
it('puts the plaintext token in the mail and the hash in the database', function (): void {
    ['invite' => $invite, 'token' => $token] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    Mail::assertQueued(InviteMail::class, fn (InviteMail $mail): bool => str_contains($mail->url, $token)
        && ! str_contains($mail->url, $invite->token_hash));
});

/**
 * Re-inviting is a normal thing to do when the first link went stale. It must
 * not error, and the old link must stop working.
 */
it('supersedes a live invite rather than refusing', function (): void {
    ['invite' => $first] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    ['invite' => $second] = resolve(CreateInvite::class)
        ->handle('her@example.test', UserRole::Member, $this->owner);

    expect($first->fresh()->isUsable())->toBeFalse()
        ->and($second->isUsable())->toBeTrue()
        ->and(Invite::query()->usable()->count())->toBe(1);
});

it('leaves an accepted invite alone when issuing a new one', function (): void {
    $accepted = Invite::factory()->accepted()->create(['email' => 'her@example.test']);

    resolve(CreateInvite::class)->handle('her@example.test', UserRole::Member, $this->owner);

    expect($accepted->fresh()->revoked_at)->toBeNull();
});

it('refuses an address that already has an account', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn (): array => resolve(CreateInvite::class)
        ->handle('taken@example.test', UserRole::Member, $this->owner))
        ->toThrow(ValidationException::class)
        ->and(Invite::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('matches an existing account case-insensitively', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn (): array => resolve(CreateInvite::class)
        ->handle('TAKEN@example.test', UserRole::Member, $this->owner))
        ->toThrow(ValidationException::class);
});
