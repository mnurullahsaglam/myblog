<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Not an AdminRequest: Profile belongs to no area, because everyone who reaches
 * the panel has to be able to set their own appearance.
 */
final class PreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accent' => ['nullable', Rule::in(AccentRamps::names())],
            'color_scheme' => ['nullable', Rule::in(Appearance::SCHEMES)],
        ];
    }
}
