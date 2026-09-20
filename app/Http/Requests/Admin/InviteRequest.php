<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Override;

final class InviteRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::General;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
