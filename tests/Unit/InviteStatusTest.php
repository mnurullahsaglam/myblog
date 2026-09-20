<?php

declare(strict_types=1);

use App\Enums\InviteStatus;
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

it('has the four states an invite can be in', function (): void {
    expect(array_column(InviteStatus::cases(), 'value'))
        ->toEqualCanonicalizing(['pending', 'accepted', 'revoked', 'expired']);
});

it('presents every case', function (InviteStatus $status): void {
    expect($status)->toBeInstanceOf(HasLabel::class)
        ->and($status)->toBeInstanceOf(HasColor::class)
        ->and($status->getLabel())->not->toBeEmpty()
        ->and($status->getColor())->not->toBeEmpty();
})->with(fn (): array => array_map(fn (InviteStatus $s): array => [$s], InviteStatus::cases()));

it('colours only pending as something a reader should act on', function (): void {
    expect(InviteStatus::Pending->getColor())->toBe('warning')
        ->and(InviteStatus::Accepted->getColor())->toBe('success')
        ->and(InviteStatus::Revoked->getColor())->toBe('gray')
        ->and(InviteStatus::Expired->getColor())->toBe('danger');
});
