<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Models\Post;
use Illuminate\Validation\Rule;
use Override;

final class PostRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::Blog;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $post = $this->route('post');
        $postId = $post instanceof Post ? $post->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($postId)],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
