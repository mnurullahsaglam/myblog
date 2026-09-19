<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('current-password'),
    ]);
});

it('renders the profile page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.profile'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Profile')
            ->where('twoFactorEnabled', false)
            ->where('twoFactorPending', false)
        );
});

it('updates the password', function (): void {
    $this->actingAs($this->admin)
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-password', $this->admin->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function (): void {
    $this->actingAs($this->admin)
        ->from(route('admin.profile'))
        ->put('/user/password', [
            'current_password' => 'nope',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('current-password', $this->admin->fresh()->password))->toBeTrue();
});

it('rejects a mismatched confirmation', function (): void {
    $this->actingAs($this->admin)
        ->from(route('admin.profile'))
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrorsIn('updatePassword', 'password');
});

it('forbids a non-admin', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'nobody@example.test']))
        ->get(route('admin.profile'))
        ->assertForbidden();
});

it('redirects a guest', function (): void {
    $this->get(route('admin.profile'))->assertRedirect('/login');
});
