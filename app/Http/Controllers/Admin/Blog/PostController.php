<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Forms\Definitions\PostForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Tables\Definitions\PostTable;

final class PostController extends AdminResourceController
{
    protected function table(): PostTable
    {
        return new PostTable;
    }

    protected function form(): PostForm
    {
        return new PostForm;
    }

    protected function modelClass(): string
    {
        return Post::class;
    }

    protected function resourceName(): string
    {
        return 'posts';
    }

    protected function pagePath(): string
    {
        return 'Blog/Posts';
    }

    protected function requestClass(): string
    {
        return PostRequest::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['image' => 'posts'];
    }
}
