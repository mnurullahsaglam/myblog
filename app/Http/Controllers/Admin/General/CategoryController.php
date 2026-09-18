<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\General;

use App\Forms\Definitions\CategoryForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Tables\Definitions\CategoryTable;
use App\Tables\ResourceTable;

class CategoryController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new CategoryTable;
    }

    protected function form(): ResourceForm
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
