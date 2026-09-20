<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/**
 * A user whose role the enum does not recognise.
 *
 * Not a state the application creates, but one a bad migration or a hand edit
 * can leave behind, and the panel must refuse it rather than guess. The column
 * is written directly because assigning an unknown value to an enum-cast
 * attribute throws before it ever reaches the database.
 */
function userWithoutRole(string $email): User
{
    $user = User::factory()->create(['email' => $email]);

    DB::table('users')->where('id', $user->getKey())->update(['role' => '']);

    return $user->fresh() ?? $user;
}
