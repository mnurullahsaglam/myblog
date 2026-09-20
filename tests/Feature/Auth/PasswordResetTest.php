<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function (): void {
    Notification::fake();
});

it('serves the forgot password page', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('serves the reset page with the token', function (): void {
    $this->get(route('password.reset', ['token' => 'a-token']).'?email=her@example.test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword'));
});

it('sends a reset link to a known address', function (): void {
    $user = User::factory()->create(['email' => 'her@example.test']);

    $this->post(route('password.email'), ['email' => 'her@example.test']);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('answers an unknown address exactly as it answers a known one', function (): void {
    User::factory()->create(['email' => 'known@example.test']);

    $known = $this->post(route('password.email'), ['email' => 'known@example.test']);
    $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.test']);

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));

    Notification::assertCount(1);
});

it('resets the password and lets her sign in with it', function (): void {
    config(['app.admin_email' => 'her@example.test']);
    $user = User::factory()->admin()->create(['email' => 'her@example.test']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('login'));

    auth()->logout();

    $this->post(route('login'), [
        'email' => 'her@example.test',
        'password' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('admin.dashboard'));
});

it('refuses a tampered reset token', function (): void {
    User::factory()->create(['email' => 'her@example.test']);

    $this->from(route('password.reset', ['token' => 'nope']))
        ->post(route('password.update'), [
            'token' => 'nope',
            'email' => 'her@example.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])
        ->assertSessionHasErrors('email');
});
