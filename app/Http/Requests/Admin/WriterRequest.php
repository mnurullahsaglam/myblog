<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Writer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WriterRequest extends FormRequest
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
        $writer = $this->route('writer');
        $writerId = $writer instanceof Writer ? $writer->id : null;
        $currentYear = (int) date('Y');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('writers', 'slug')->ignore($writerId)],
            'image' => ['nullable', 'image', 'max:5120'],
            'bio' => ['nullable', 'string', 'max:65535'],
            'birth_year' => ['nullable', 'integer', 'min:0', 'max:'.$currentYear],
            'death_year' => ['nullable', 'integer', 'min:0', 'max:'.$currentYear, 'gte:birth_year'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'death_place' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'death_year.gte' => 'The year of death cannot be before the year of birth.',
        ];
    }
}
