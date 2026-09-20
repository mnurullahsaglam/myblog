<?php

declare(strict_types=1);

namespace App\Models;

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
use Override;

/**
 * @property UserRole|null $role Null when the column holds a value the enum does not know
 */
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
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

    /**
     * Whether this user reaches an area.
     *
     * ADMIN_EMAIL is checked first and unconditionally: a bad migration, an empty
     * column or a fat-fingered seed must never lock the owner out of their own
     * panel.
     */
    public function canAccess(Area $area): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->role instanceof UserRole && in_array($area, $this->role->areas(), true);
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
        ];
    }
}
