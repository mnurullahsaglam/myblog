<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Override;

final class ClientRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Work;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'tax_no' => ['required', 'string', 'max:255'],
        ];
    }
}
