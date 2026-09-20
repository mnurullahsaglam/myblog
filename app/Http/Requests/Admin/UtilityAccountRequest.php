<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Enums\UtilityType;
use Illuminate\Validation\Rule;
use Override;

final class UtilityAccountRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Utilities;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(UtilityType::class)],
            'provider' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'subscriber_no' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
