<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending invitation to join the panel.
 *
 * @property string $email
 * @property string $token_hash
 * @property UserRole $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property-read InviteStatus $status
 */
final class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    /**
     * Usable means: issued, not spent, not withdrawn, not stale. Every refusal
     * in this feature reduces to the negation of this one method.
     */
    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Acceptance and revocation outrank expiry: an invite that was spent and
     * then sat past its window is spent, not stale.
     */
    public function getStatusAttribute(): InviteStatus
    {
        return match (true) {
            $this->accepted_at instanceof Carbon => InviteStatus::Accepted,
            $this->revoked_at instanceof Carbon => InviteStatus::Revoked,
            $this->expires_at->isPast() => InviteStatus::Expired,
            default => InviteStatus::Pending,
        };
    }

    /**
     * @param  Builder<Invite>  $query
     * @return Builder<Invite>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
