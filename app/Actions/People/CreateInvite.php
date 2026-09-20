<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\UserRole;
use App\Mail\InviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issue an invitation and send it.
 *
 * At most one invite per address may be usable at a time. That rule lives here
 * rather than in a database index because the index that would express it is a
 * partial unique index, which SQLite supports and MySQL does not — and this
 * application develops on MySQL while its CI runs SQLite.
 */
final class CreateInvite
{
    /**
     * @return array{invite: Invite, token: string}
     *
     * @throws ValidationException
     */
    public function handle(string $email, UserRole $role, ?User $invitedBy = null): array
    {
        $email = mb_strtolower(trim($email));

        $adminEmail = config('app.admin_email');

        if (is_string($adminEmail) && $adminEmail !== '' && $email === mb_strtolower($adminEmail)) {
            throw ValidationException::withMessages([
                'email' => 'That address is reserved.',
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'That address already has an account.',
            ]);
        }

        // Exists only here and in what this method returns. After that, the row
        // holds a hash and the plaintext is unrecoverable.
        $token = Str::random(64);

        $invite = DB::transaction(function () use ($email, $role, $invitedBy, $token): Invite {
            Invite::query()
                ->where('email', $email)
                ->usable()
                ->update(['revoked_at' => now()]);

            return Invite::create([
                'email' => $email,
                'role' => $role->value,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
                'invited_by' => $invitedBy?->getKey(),
            ]);
        });

        $appName = config('app.name');

        // Sent outside the transaction on purpose: a transport failure must not
        // roll back an invite whose link the panel is about to display, because
        // that link is the fallback for exactly this case.
        Mail::to($email)->send(new InviteMail(
            url: route('invite.show', $token),
            invitedByName: $invitedBy->name ?? (is_string($appName) ? $appName : 'The panel'),
        ));

        return ['invite' => $invite, 'token' => $token];
    }
}
