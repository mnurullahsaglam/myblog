<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The inner fence.
 *
 * Route groups are the outer one; neither depends on the other being right.
 * area() is abstract on purpose: a new request cannot be written without saying
 * where it belongs, so the check stops being something to remember.
 */
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
