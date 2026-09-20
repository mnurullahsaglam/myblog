<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;

enum InviteStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Revoked => 'gray',
            self::Expired => 'danger',
        };
    }
}
