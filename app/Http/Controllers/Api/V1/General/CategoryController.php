<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\General;

use App\Forms\Definitions\CategoryForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Tables\Definitions\CategoryTable;

final class CategoryController extends ApiResourceController
{
    protected function table(): CategoryTable
    {
        return new CategoryTable;
    }

    protected function form(): CategoryForm
    {
        return new CategoryForm;
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function resourceName(): string
    {
        return 'categories';
    }

    protected function requestClass(): string
    {
        return CategoryRequest::class;
    }

    protected function resourceClass(): string
    {
        return CategoryResource::class;
    }
}
