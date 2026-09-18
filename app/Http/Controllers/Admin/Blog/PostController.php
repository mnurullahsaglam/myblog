<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Forms\Definitions\PostForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Tables\Definitions\PostTable;
use App\Tables\ResourceTable;

class PostController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new PostTable;
    }

    protected function form(): ResourceForm
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
