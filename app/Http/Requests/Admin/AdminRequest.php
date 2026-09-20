<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Illuminate\Foundation\Http\FormRequest;

abstract class AdminRequest extends FormRequest
{
    abstract protected function area(): Area;

    /**
     * @return array<string, array<int, mixed>>
     */
    abstract public function rules(): array;

    public function authorize(): bool
    {
        return $this->user()?->can('access-area', $this->area()) ?? false;
    }
}
