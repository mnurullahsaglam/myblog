<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Blog;

use App\Forms\Definitions\PostForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use App\Tables\Definitions\PostTable;

final class PostController extends ApiResourceController
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

    protected function requestClass(): string
    {
        return PostRequest::class;
    }

    protected function resourceClass(): string
    {
        return PostResource::class;
    }
}
