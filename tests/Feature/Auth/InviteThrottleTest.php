<?php

declare(strict_types=1);

use App\Actions\People\CreateInvite;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('throttles attempts on the invite link', function (): void {
    Mail::fake();
    config(['app.admin_email' => 'owner@example.test']);
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    ['token' => $token] = resolve(CreateInvite::class)->handle('her@example.test', UserRole::Member, $owner);

    foreach (range(1, 6) as $ignored) {
        $this->get(route('invite.show', $token))->assertOk();
    }

    $this->get(route('invite.show', $token))->assertStatus(429);
});
