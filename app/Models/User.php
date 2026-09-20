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
 * @property UserRole $role
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
        return in_array($area, $this->areas(), true);
    }

    /**
     * Every area this user reaches. Empty means they do not belong in the panel
     * at all.
     *
     * The column is read raw rather than through the cast: the cast throws on a
     * value the enum does not know, and an unreadable role must mean no access
     * rather than a 500 on every page.
     *
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
        ];
    }
}
