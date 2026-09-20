<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UtilityAccount;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

it('lists accounts', function (): void {
    UtilityAccount::factory()->count(3)->create();

    $this->get(route('admin.utility-accounts.index'))->assertOk();
});

it('creates an account', function (): void {
    $this->post(route('admin.utility-accounts.store'), [
        'type' => 'electricity',
        'provider' => 'Enerjisa',
        'label' => 'Ev elektrik',
        'subscriber_no' => '1234567890',
        'is_active' => true,
    ])->assertRedirect(route('admin.utility-accounts.index'));

    expect(UtilityAccount::where('label', 'Ev elektrik')->exists())->toBeTrue();
});

it('refuses a type outside the enum', function (string $type): void {
    $this->from(route('admin.utility-accounts.index'))
        ->post(route('admin.utility-accounts.store'), [
            'type' => $type,
            'provider' => 'Enerjisa',
            'label' => 'Ev elektrik',
        ])
        ->assertSessionHasErrors('type');
})->with(['gasoline', 'ELECTRICITY', '', User::class]);

it('requires a provider and a label', function (): void {
    $this->from(route('admin.utility-accounts.index'))
        ->post(route('admin.utility-accounts.store'), ['type' => 'water'])
        ->assertSessionHasErrors(['provider', 'label']);
});

it('updates an account', function (): void {
    $account = UtilityAccount::factory()->create(['label' => 'Before']);

    $this->put(route('admin.utility-accounts.update', $account), [
        'type' => $account->type->value,
        'provider' => $account->provider,
        'label' => 'After',
        'is_active' => true,
    ])->assertRedirect(route('admin.utility-accounts.index'));

    expect($account->refresh()->label)->toBe('After');
});

it('deletes an account', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->delete(route('admin.utility-accounts.destroy', $account))
        ->assertRedirect(route('admin.utility-accounts.index'));

    expect(UtilityAccount::count())->toBe(0);
});

it('turns a guest away', function (): void {
    auth()->logout();

    $this->get(route('admin.utility-accounts.index'))->assertRedirect(route('login'));
});

/**
 * Route::resource names the parameter {utility_account} while the resource is
 * "utility-accounts", so the base controller has to convert the hyphen. This is
 * the first hyphenated resource with an edit route; the bug was latent until now.
 */
it('resolves a hyphenated resource record from the route', function (): void {
    $account = UtilityAccount::factory()->create();

    $this->get(route('admin.utility-accounts.edit', $account))->assertOk();
});
