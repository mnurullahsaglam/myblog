<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AdminAlert;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
});

it('answers 404 rather than erroring on an id that is not a uuid', function (string $verb, string $id): void {
    $route = $verb === 'read' ? 'admin.notifications.read' : 'admin.notifications.destroy';

    $response = $verb === 'read'
        ? $this->actingAs($this->owner)->patch(route($route, $id))
        : $this->actingAs($this->owner)->delete(route($route, $id));

    $response->assertNotFound();
})->with([
    'read, plain text' => ['read', 'not-a-real-id'],
    'read, sql-ish' => ['read', "1' OR '1'='1"],
    'destroy, plain text' => ['destroy', 'not-a-real-id'],
    'destroy, empty-ish' => ['destroy', '0'],
]);

it('still marks a real notification as read', function (): void {
    $this->owner->notify(new AdminAlert('Real'));
    $id = (string) $this->owner->unreadNotifications()->sole()->getKey();

    $this->actingAs($this->owner)->patch(route('admin.notifications.read', $id))->assertRedirect();

    expect($this->owner->fresh()->unreadNotifications()->count())->toBe(0);
});

it('still dismisses a real notification', function (): void {
    $this->owner->notify(new AdminAlert('Real'));
    $id = (string) $this->owner->notifications()->sole()->getKey();

    $this->actingAs($this->owner)->delete(route('admin.notifications.destroy', $id))->assertRedirect();

    expect($this->owner->fresh()->notifications()->count())->toBe(0);
});
