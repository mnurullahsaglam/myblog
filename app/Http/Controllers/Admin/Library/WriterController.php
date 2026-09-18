<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Forms\Definitions\WriterForm;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Requests\Admin\WriterRequest;
use App\Models\Writer;
use App\Tables\Definitions\WriterTable;
use App\Tables\ResourceTable;

class WriterController extends AdminResourceController
{
    protected function table(): ResourceTable
    {
        return new WriterTable;
    }

    protected function form(): ResourceForm
    {
        return new WriterForm;
    }

    protected function modelClass(): string
    {
        return Writer::class;
    }

    protected function resourceName(): string
    {
        return 'writers';
    }

    protected function pagePath(): string
    {
        return 'Library/Writers';
    }

    protected function requestClass(): string
    {
        return WriterRequest::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['image' => 'writers'];
    }
}
