<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Income;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('starts and stops', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', true));

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->delete(route('admin.preview.destroy'));

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', false));
});

it('hides the same routes from him as from her', function (string $routeName): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)->get(route($routeName))->assertNotFound();
})->with(['admin.posts.index', 'admin.clients.index', 'admin.settings']);

/**
 * The assertion that makes a preview worth having. Comparing the navigation
 * both sessions are handed proves the preview is honest rather than decorative.
 */
it('shows the owner the navigation her own session shows', function (): void {
    $hers = null;
    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(function ($page) use (&$hers): void {
            $hers = $page->toArray()['props']['navigation'];
        });

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(function ($page) use (&$hers): void {
            expect($page->toArray()['props']['navigation'])->toEqual($hers);
        });
});

it('hides the same dashboard panels from him as from her', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('budget')
            ->has('library')
            ->missing('work')
            ->missing('recentPosts'));
});

it('hides the client from him while previewing', function (): void {
    $client = Client::factory()->create(['title' => 'Zzpreview Client']);
    Income::factory()->create(['client_id' => $client->id]);

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    expect($this->actingAs($this->owner)->get(route('admin.incomes.index'))->getContent())
        ->not->toContain('Zzpreview Client');
});

it('refuses every write while active', function (): void {
    $income = Income::factory()->create(['client_id' => null]);

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->put(route('admin.incomes.update', $income), [
            'amount' => 999, 'currency' => 'TRY', 'date' => now()->toDateString(), 'description' => 'Changed',
        ])
        ->assertForbidden();

    expect($income->fresh()->description)->not->toBe('Changed');
});

it('still allows leaving the preview', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->delete(route('admin.preview.destroy'))
        ->assertRedirect();
});

it('refuses to start a preview for a member', function (): void {
    $this->actingAs($this->member)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value])
        ->assertNotFound();
});

/**
 * A preview must not be a way to climb: previewing a role no narrower than the
 * current one, or starting one from inside another, both have to fail.
 */
it('refuses to preview a role that is not narrower', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Admin->value])
        ->assertForbidden();
});

it('refuses to start a preview from inside one', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value])
        ->assertForbidden();
});

it('refuses a role that does not exist', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => 'wizard'])
        ->assertStatus(422);
});

it('ends on sign out', function (): void {
    $this->actingAs($this->owner)
        ->from(route('admin.dashboard'))
        ->post(route('admin.preview.store'), ['role' => UserRole::Member->value]);

    $this->actingAs($this->owner)->post(route('logout'));

    $this->actingAs($this->owner->fresh())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.active', false));
});

it('offers the control to the owner and not to her', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.available', true));

    $this->actingAs($this->member)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('preview.available', false));
});
