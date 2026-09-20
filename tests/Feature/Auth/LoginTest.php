<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
});

it('renders the login page', function (): void {
    $this->get('/login')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Auth/Login'));
});

it('logs the admin in with valid credentials', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->post('/login', [
        'email' => 'admin@example.test',
        'password' => 'correct-horse-battery',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
});

it('rejects a wrong password', function (): void {
    User::factory()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->from('/login')
        ->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects an unknown email', function (): void {
    $this->from('/login')
        ->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires an email and a password', function (): void {
    $this->from('/login')
        ->post('/login', [])
        ->assertSessionHasErrors(['email', 'password']);
});

it('logs out', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.test']);

    $this->actingAs($admin)->post('/logout')->assertRedirect();

    $this->assertGuest();
});

it('does not expose a registration route', function (): void {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

it('exposes password reset but still not registration', function (): void {
    expect(Route::has('password.request'))->toBeTrue()
        ->and(Route::has('password.reset'))->toBeTrue()
        ->and(Route::has('register'))->toBeFalse();
});

it('sends an authenticated visitor to the panel', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.test']);

    $this->actingAs($admin)->get('/login')->assertRedirect('/admin');
});
