<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Library;

use App\Forms\Definitions\WriterForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\WriterRequest;
use App\Http\Resources\Api\V1\WriterResource;
use App\Models\Writer;
use App\Tables\Definitions\WriterTable;

final class WriterController extends ApiResourceController
{
    protected function table(): WriterTable
    {
        return new WriterTable;
    }

    protected function form(): WriterForm
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

    protected function requestClass(): string
    {
        return WriterRequest::class;
    }

    protected function resourceClass(): string
    {
        return WriterResource::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['image' => 'writers'];
    }
}
