<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AdminAlert;
use App\Support\AdminNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/__notify/{variant}', function (string $variant): string {
        resolve(AdminNotifier::class)->{$variant}('Saved', 'The record was saved.');

        return 'ok';
    });

    Route::middleware('web')->get('/__notify-bare', function (): string {
        resolve(AdminNotifier::class)->danger('Something broke');

        return 'ok';
    });
});

it('flashes a success notification during a request', function (): void {
    $this->get('/__notify/success')->assertOk();

    expect(session(AdminNotifier::SESSION_KEY))->toBe([
        'variant' => 'success',
        'title' => 'Saved',
        'body' => 'The record was saved.',
    ]);
});

it('flashes a danger notification without a body', function (): void {
    $this->get('/__notify-bare')->assertOk();

    expect(session(AdminNotifier::SESSION_KEY))->toBe([
        'variant' => 'danger',
        'title' => 'Something broke',
        'body' => null,
    ]);
});

it('flashes an info notification', function (): void {
    $this->get('/__notify/info')->assertOk();

    expect(session(AdminNotifier::SESSION_KEY))->toHaveKey('variant', 'info');
});

it('logs instead of flashing when there is no session to flash into', function (): void {
    Log::spy();

    new AdminNotifier(forceLog: true)->info('Sync finished', '42 rows');

    Log::shouldHaveReceived('info')->once()->with('Sync finished', ['body' => '42 rows']);
    expect(session()->has(AdminNotifier::SESSION_KEY))->toBeFalse();
});

it('resolves from the container without forcing the log', function (): void {
    expect(resolve(AdminNotifier::class))->toBeInstanceOf(AdminNotifier::class);
});

it('stores an admin alert as a database notification', function (): void {
    $user = User::factory()->create();

    $user->notify(new AdminAlert('WakaTime sync failed', 'Token expired.', 'danger'));

    expect($user->notifications()->firstOrFail()->data)->toBe([
        'title' => 'WakaTime sync failed',
        'body' => 'Token expired.',
        'variant' => 'danger',
    ]);
});

it('defaults an admin alert to the info variant', function (): void {
    $user = User::factory()->create();

    expect(new AdminAlert('Heads up')->toDatabase($user))->toBe([
        'title' => 'Heads up',
        'body' => null,
        'variant' => 'info',
    ]);
});

it('routes admin alerts to the database channel only', function (): void {
    expect(new AdminAlert('x')->via(User::factory()->create()))->toBe(['database']);
});
