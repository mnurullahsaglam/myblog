<?php

declare(strict_types=1);

use App\Actions\Notifications\DismissNotification;
use App\Models\User;
use App\Notifications\AdminAlert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function notifyOnce(User $user, string $title = 'Heads up'): string
{
    $user->notify(new AdminAlert($title, 'Body'));

    return (string) $user->notifications()->latest()->first()->id;
}

it('deletes a notification belonging to the user', function (): void {
    $user = User::factory()->create();
    $id = notifyOnce($user);

    resolve(DismissNotification::class)->handle($user, $id);

    expect($user->notifications()->count())->toBe(0);
});

it("refuses to delete another user's notification", function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $id = notifyOnce($owner, 'Private');

    expect(fn () => resolve(DismissNotification::class)->handle($intruder, $id))
        ->toThrow(NotFoundHttpException::class)
        ->and($owner->notifications()->count())->toBe(1);
});

it('refuses an id that does not exist', function (): void {
    $user = User::factory()->create();

    expect(fn () => resolve(DismissNotification::class)->handle($user, 'not-a-real-id'))
        ->toThrow(NotFoundHttpException::class);
});

it('leaves the user other notifications alone', function (): void {
    $user = User::factory()->create();
    $first = notifyOnce($user, 'First');
    notifyOnce($user, 'Second');

    resolve(DismissNotification::class)->handle($user, $first);

    expect($user->notifications()->count())->toBe(1);
});
