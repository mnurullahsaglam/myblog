<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Override;

final class MakeAdmin extends Command
{
    #[Override]
    protected $signature = 'make:admin
                            {--name= : The display name for the account}
                            {--email= : The address the account signs in with}
                            {--password= : Skips the prompt, at the cost of leaving the password in your shell history}
                            {--force : Promote an existing account without asking first}';

    #[Override]
    protected $description = 'Create an administrator, or promote an existing account to one';

    public function handle(): int
    {
        $name = $this->answer('name', 'Name');
        $email = $this->answer('email', 'Email');
        $password = $this->answer('password', 'Password', hidden: true);

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing instanceof User) {
            return $this->promote($existing, $name, $password);
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'role' => UserRole::Admin,
        ]);

        $this->info($email.' created as an administrator.');

        return self::SUCCESS;
    }

    private function promote(User $user, string $name, string $password): int
    {
        $confirmed = $this->option('force') === true || $this->confirm(
            $user->email.' already exists. Reset its password and make it an administrator?',
            default: false,
        );

        if (! $confirmed) {
            $this->warn('Nothing changed.');

            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'role' => UserRole::Admin,
        ])->save();

        $this->info($user->email.' is now an administrator.');

        return self::SUCCESS;
    }

    private function answer(string $option, string $question, bool $hidden = false): string
    {
        $given = $this->option($option);

        if (is_string($given) && $given !== '') {
            return $given;
        }

        $answer = $hidden ? $this->secret($question) : $this->ask($question);

        return is_string($answer) ? $answer : '';
    }
}
