<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BookRequest extends FormRequest
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
        $book = $this->route('book');
        $bookId = $book instanceof Book ? $book->id : null;

        return [
            'writer_id' => ['required', 'integer', 'exists:writers,id'],
            'publisher_id' => ['required', 'integer', 'exists:publishers,id'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($bookId)],
            'original_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'publication_date' => ['nullable', 'integer', 'min:0', 'max:'.(int) date('Y')],
            'publication_location' => ['nullable', 'string', 'max:255'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:5120'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
