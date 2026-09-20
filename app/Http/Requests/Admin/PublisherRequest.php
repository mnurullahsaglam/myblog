<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Models\Publisher;
use Illuminate\Validation\Rule;
use Override;

final class PublisherRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Library;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $publisher = $this->route('publisher');
        $publisherId = $publisher instanceof Publisher ? $publisher->id : null;

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('publishers', 'slug')->ignore($publisherId)],
        ];
    }
}
