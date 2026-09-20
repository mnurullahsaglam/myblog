<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('throttles requests for a reset link', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'her@example.test']);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.email'), ['email' => 'her@example.test'])->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => 'her@example.test'])->assertStatus(429);
});
