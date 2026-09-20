<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotTheAdminAddress;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;

final class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
                new NotTheAdminAddress($user->email),
            ],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email) {
            $this->updateChangedEmail($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * @param  array<string, string>  $input
     */
    private function updateChangedEmail(User $user, array $input): void
    {
        $verifying = Features::enabled(Features::emailVerification());

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => $verifying ? null : $user->email_verified_at,
        ])->save();

        if ($verifying) {
            $user->sendEmailVerificationNotification();
        }
    }
}
