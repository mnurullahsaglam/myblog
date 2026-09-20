<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class AcceptInvite
{
    /**
     * Spend an invite and create the account it was for.
     *
     * The invite is re-read under a row lock rather than trusted as passed: two
     * requests arriving together must produce one user, and the lock is what
     * makes that true rather than merely likely. The unique index on
     * users.email is the backstop if the lock is ever lost to a refactor.
     */
    public function handle(Invite $invite, string $name, string $password): User
    {
        return DB::transaction(function () use ($invite, $name, $password): User {
            $locked = Invite::query()->lockForUpdate()->find($invite->getKey());

            throw_if(! $locked instanceof Invite || ! $locked->isUsable(), RuntimeException::class, 'This invitation is no longer usable.');

            throw_if(
                User::query()->whereRaw('LOWER(email) = ?', [$locked->email])->exists(),
                RuntimeException::class,
                'That address already has an account.',
            );

            $user = User::create([
                'name' => $name,
                'email' => $locked->email,
                'password' => Hash::make($password),
                'role' => $locked->role->value,
                // Opening a link sent to an address proves control of it at
                // least as well as a second mail would.
                'email_verified_at' => now(),
            ]);

            $locked->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
