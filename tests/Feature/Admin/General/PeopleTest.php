<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('serves the page to the owner', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('General/People/Index')
            ->has('users')
            ->has('invites'));
});

it('hides the page from a member entirely', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.people.index'))
        ->assertNotFound();
});

it('creates an invite and shows the link once', function (): void {
    $this->actingAs($this->owner)
        ->post(route('admin.people.store'), [
            'email' => 'new@example.test',
            'role' => UserRole::Member->value,
        ])
        ->assertRedirect(route('admin.people.index'));

    $invite = Invite::query()->where('email', 'new@example.test')->sole();

    expect($invite->role)->toBe(UserRole::Member)
        ->and(session('flash.invite_url'))->toContain('/invite/');

    Mail::assertQueuedCount(1);
});

it('refuses an invite from a member', function (): void {
    $this->actingAs($this->member)
        ->post(route('admin.people.store'), [
            'email' => 'new@example.test',
            'role' => UserRole::Member->value,
        ])
        ->assertNotFound();

    expect(Invite::query()->count())->toBe(0);
});

it('validates the invite form', function (array $payload, string $field): void {
    $this->actingAs($this->owner)
        ->from(route('admin.people.index'))
        ->post(route('admin.people.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'no email' => [['role' => 'member'], 'email'],
    'not an email' => [['email' => 'nope', 'role' => 'member'], 'email'],
    'unknown role' => [['email' => 'a@example.test', 'role' => 'wizard'], 'role'],
    'existing account' => [['email' => 'her@example.test', 'role' => 'member'], 'email'],
]);

it('revokes an invite', function (): void {
    $invite = Invite::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('admin.people.revoke', $invite))
        ->assertRedirect(route('admin.people.index'));

    expect($invite->fresh()->revoked_at)->not->toBeNull();
    Mail::assertNothingQueued();
});

it('refuses to revoke from a member', function (): void {
    $invite = Invite::factory()->create();

    $this->actingAs($this->member)
        ->delete(route('admin.people.revoke', $invite))
        ->assertNotFound();

    expect($invite->fresh()->revoked_at)->toBeNull();
});

it('reissues rather than resending, because the token cannot be recovered', function (): void {
    $invite = Invite::factory()->create(['email' => 'new@example.test']);
    $hash = $invite->token_hash;

    $this->actingAs($this->owner)
        ->post(route('admin.people.reissue', $invite))
        ->assertRedirect(route('admin.people.index'));

    expect($invite->fresh()->revoked_at)->not->toBeNull('the old invite is superseded');

    $fresh = Invite::query()->where('email', 'new@example.test')->usable()->sole();

    expect($fresh->token_hash)->not->toBe($hash);
    Mail::assertQueuedCount(1);
});

it('refuses to reissue an invite that is no longer usable', function (): void {
    $invite = Invite::factory()->revoked()->create();

    $this->actingAs($this->owner)
        ->post(route('admin.people.reissue', $invite))
        ->assertNotFound();

    Mail::assertNothingQueued();
});

it('lists both users in the table', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.people.index'))
        ->assertOk()
        ->assertSee('owner@example.test')
        ->assertSee('her@example.test');
});
