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
                'email_verified_at' => now(),
            ]);

            $locked->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
