<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Publisher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
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
