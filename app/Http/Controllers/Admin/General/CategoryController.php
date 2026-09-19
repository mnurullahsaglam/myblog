<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\General;

use App\Forms\Definitions\CategoryForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Tables\Definitions\CategoryTable;

final class CategoryController extends AdminResourceController
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

    protected function pagePath(): string
    {
        return 'General/Categories';
    }

    protected function requestClass(): string
    {
        return CategoryRequest::class;
    }
}
