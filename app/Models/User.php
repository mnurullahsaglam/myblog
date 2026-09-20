<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Override;

/**
 * @property UserRole $role
 * @property array<string, mixed>|null $preferences
 */
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    #[Override]
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function isAdmin(): bool
    {
        $adminEmail = config('app.admin_email');

        return is_string($adminEmail) && $adminEmail !== '' && $this->email === $adminEmail;
    }

    public function canAccess(Area $area): bool
    {
        return in_array($area, $this->areas(), true);
    }

    /**
     * @return array<int, Ability>
     */
    public function abilities(): array
    {
        if ($this->isAdmin()) {
            return Ability::cases();
        }

        $stored = $this->getAttributes()['role'] ?? null;

        return (is_string($stored) ? UserRole::tryFrom($stored) : null)?->abilities() ?? [];
    }

    /**
     * @return array<int, Area>
     */
    public function areas(): array
    {
        if ($this->isAdmin()) {
            return Area::cases();
        }

        $stored = $this->getAttributes()['role'] ?? null;

        return (is_string($stored) ? UserRole::tryFrom($stored) : null)?->areas() ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'preferences' => 'array',
        ];
    }
}
