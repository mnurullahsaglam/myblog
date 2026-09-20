<?php

declare(strict_types=1);

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use App\Models\Invite;

it('is pending when nothing has happened to it', function (): void {
    expect(Invite::factory()->create()->status)->toBe(InviteStatus::Pending);
});

it('is accepted once accepted', function (): void {
    expect(Invite::factory()->accepted()->create()->status)->toBe(InviteStatus::Accepted);
});

it('is revoked once revoked', function (): void {
    expect(Invite::factory()->revoked()->create()->status)->toBe(InviteStatus::Revoked);
});

it('is expired once its moment has passed', function (): void {
    expect(Invite::factory()->expired()->create()->status)->toBe(InviteStatus::Expired);
});

it('reports an accepted invite as accepted even after its window closes', function (): void {
    $invite = Invite::factory()->accepted()->expired()->create();

    expect($invite->status)->toBe(InviteStatus::Accepted);
});

it('reports a revoked invite as revoked even after its window closes', function (): void {
    expect(Invite::factory()->revoked()->expired()->create()->status)->toBe(InviteStatus::Revoked);
});

it('is usable only while pending', function (): void {
    expect(Invite::factory()->create()->isUsable())->toBeTrue()
        ->and(Invite::factory()->accepted()->create()->isUsable())->toBeFalse()
        ->and(Invite::factory()->revoked()->create()->isUsable())->toBeFalse()
        ->and(Invite::factory()->expired()->create()->isUsable())->toBeFalse();
});

it('finds only usable invites through the scope', function (): void {
    $pending = Invite::factory()->create();
    Invite::factory()->accepted()->create();
    Invite::factory()->revoked()->create();
    Invite::factory()->expired()->create();

    expect(Invite::query()->usable()->pluck('id')->all())->toBe([$pending->id]);
});

it('casts the role to the enum', function (): void {
    $invite = Invite::factory()->create(['role' => UserRole::Admin->value]);

    expect($invite->role)->toBe(UserRole::Admin);
});

it('never stores the plaintext token', function (): void {
    $invite = Invite::factory()->create();

    expect($invite->token_hash)->toHaveLength(64)
        ->and($invite->getAttributes())->not->toHaveKey('token');
});
