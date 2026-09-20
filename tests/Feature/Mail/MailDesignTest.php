<?php

declare(strict_types=1);

use App\Mail\InviteMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;

function invite(): string
{
    return new InviteMail('https://myblog.test/invite/abc', 'Nurullah')->render();
}

function inviteTextBody(): string
{
    Mail::to('invitee@example.test')->sendNow(new InviteMail('https://myblog.test/invite/abc', 'Nurullah'));

    $sent = Mail::getSymfonyTransport()->messages()->last();

    return (string) $sent?->getOriginalMessage()->getTextBody();
}

it('carries no framework branding', function (): void {
    $html = invite();

    expect($html)->not->toContain('laravel.com')
        ->and($html)->not->toContain('notification-logo')
        ->and($html)->not->toContain('All rights reserved')
        ->and($html)->not->toContain('<img');
});

it('paints the panel rather than the framework default', function (): void {
    $html = invite();

    expect($html)->toContain('#FFFFFF')
        ->and($html)->toContain('#D9D9D2')
        ->and($html)->toContain('Inter')
        ->and($html)->toContain('JetBrains Mono')
        ->and($html)->not->toContain('#3869d4');
});

it('keeps quoted font names intact', function (): void {
    expect(invite())->toContain("'Segoe UI'")
        ->and(invite())->not->toContain('&#039;');
});

it('renders light for an invitee even when the household reads dark', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');

    $html = invite();

    expect($html)->toContain('background-color: #F7F7F5')
        ->and($html)->toContain('content="light"')
        ->and($html)->not->toContain('#0D0E11');
});

it('fills the button with the household accent and never white text', function (): void {
    Setting::set('appearance', 'accent', 'emerald');

    $html = invite();

    expect($html)->toContain('background-color: #10B981')
        ->and($html)->toContain('color: #141517');
});

it('inlines its styles, because no mail client reads a stylesheet', function (): void {
    $html = invite();

    expect($html)->toContain('<h1 style="color: #1A1B1E')
        ->and($html)->toContain('<p style="color: #5A606E');
});

it('keeps the responsive rules a style attribute cannot express', function (): void {
    expect(invite())->toContain('@media only screen and (max-width: 600px)');
});

it('sends a plain text alternative alongside the html', function (): void {
    $text = inviteTextBody();

    expect($text)->toContain('You have been invited')
        ->and($text)->toContain('https://myblog.test/invite/abc')
        ->and($text)->not->toContain('All rights reserved')
        ->and($text)->not->toContain('<');
});

it('renders the password reset through our own template, not the framework one', function (): void {
    $user = User::factory()->create(['preferences' => ['color_scheme' => 'dark', 'accent' => 'violet']]);

    $html = (string) new ResetPassword('reset-token')->toMail($user)->render();

    expect($html)->toContain('Reset your password')
        ->and($html)->toContain('reset-password/reset-token')
        ->and($html)->toContain('background-color: #0D0E11')
        ->and($html)->toContain('background-color: #A78BFA')
        ->and($html)->toContain('content="dark"')
        ->and($html)->not->toContain('laravel.com')
        ->and($html)->not->toContain('All rights reserved');
});

it('tells the reset link how long it lasts', function (): void {
    config(['auth.passwords.users.expire' => 45]);

    $html = (string) new ResetPassword('reset-token')->toMail(User::factory()->create())->render();

    expect($html)->toContain('45 minutes');
});

it('subjects the reset with the application name', function (): void {
    $message = new ResetPassword('reset-token')->toMail(User::factory()->create());

    expect($message->subject)->toBe('Reset your '.config('app.name').' password');
});

it('never lets a Turkish locale uppercase English copy', function (): void {
    app()->setLocale('tr');

    $html = invite();

    expect(substr_count($html, 'text-transform: uppercase'))->toBe(1)
        ->and($html)->toContain('because someone asked for it')
        ->and($html)->not->toContain('İ');
});
