<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
    $this->actingAs($this->admin);
});

function notifyAdmin(User $user, string $title = 'Sync finished', ?string $body = '42 rows'): string
{
    $user->notify(new AdminAlert($title, $body, 'danger'));

    return (string) $user->notifications()->latest()->first()->id;
}

it('shares an empty state when there are none', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unreadCount', 0)
            ->has('notifications.items', 0)
        );
});

it('shares unread notifications with their payload', function (): void {
    notifyAdmin($this->admin);
    notifyAdmin($this->admin);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unreadCount', 2)
            ->has('notifications.items', 2)
            ->where('notifications.items.0.title', 'Sync finished')
            ->where('notifications.items.0.body', '42 rows')
            ->where('notifications.items.0.variant', 'danger')
            ->where('notifications.items.0.readAt', null)
        );
});

it('counts only unread but lists read ones too', function (): void {
    notifyAdmin($this->admin);
    $read = notifyAdmin($this->admin);

    DB::table('notifications')->where('id', $read)->update(['read_at' => now()]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.items', 2)
        );
});

it('falls back gracefully for an unrecognised payload', function (): void {
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Legacy',
        'notifiable_type' => $this->admin::class,
        'notifiable_id' => $this->admin->id,
        'data' => json_encode(['something' => 'else']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.items.0.title', 'Notification')
            ->where('notifications.items.0.body', null)
            ->where('notifications.items.0.variant', 'info')
        );
});

it('marks one notification as read', function (): void {
    $id = notifyAdmin($this->admin);

    $this->patch(route('admin.notifications.read', $id))->assertRedirect();

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->not->toBeNull();
});

it('marks everything as read', function (): void {
    notifyAdmin($this->admin);
    notifyAdmin($this->admin);

    $this->patch(route('admin.notifications.read-all'))->assertRedirect();

    expect(DB::table('notifications')->whereNull('read_at')->count())->toBe(0);
});

it('deletes a notification', function (): void {
    $id = notifyAdmin($this->admin);

    $this->delete(route('admin.notifications.destroy', $id))->assertRedirect();

    expect(DB::table('notifications')->where('id', $id)->exists())->toBeFalse();
});

it('cannot touch another user notification', function (): void {
    $other = User::factory()->create(['email' => 'other@example.test']);
    $id = notifyAdmin($other);

    $this->patch(route('admin.notifications.read', $id))->assertNotFound();
    $this->delete(route('admin.notifications.destroy', $id))->assertNotFound();

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->toBeNull()
        ->and(DB::table('notifications')->where('id', $id)->exists())->toBeTrue();
});

it('caps the shared list but not the count', function (): void {
    for ($i = 0; $i < 20; $i++) {
        notifyAdmin($this->admin);
    }

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('notifications.items', 15)
            ->where('notifications.unreadCount', 20)
        );
});

it('shares nothing for a guest', function (): void {
    auth()->logout();

    $this->get('/login')->assertOk();
});
