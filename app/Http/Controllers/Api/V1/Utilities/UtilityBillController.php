<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Utilities;

use App\Actions\Utilities\SaveBillLines;
use App\Forms\Definitions\UtilityBillForm;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Requests\Admin\UtilityBillRequest;
use App\Http\Resources\Api\V1\UtilityBillResource;
use App\Models\UtilityBill;
use App\Tables\Definitions\UtilityBillTable;
use Illuminate\Database\Eloquent\Model;
use Override;

final class UtilityBillController extends ApiResourceController
{
    protected function table(): UtilityBillTable
    {
        return new UtilityBillTable;
    }

    protected function form(): UtilityBillForm
    {
        return new UtilityBillForm;
    }

    protected function modelClass(): string
    {
        return UtilityBill::class;
    }

    protected function resourceName(): string
    {
        return 'utility-bills';
    }

    protected function requestClass(): string
    {
        return UtilityBillRequest::class;
    }

    protected function resourceClass(): string
    {
        return UtilityBillResource::class;
    }

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return ['document_path' => 'utility-bills'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    protected function pullChildren(array &$data): array
    {
        return $this->form()->pullLines($data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     */
    #[Override]
    protected function writeChildren(Model $record, array $children): void
    {
        if (! $record instanceof UtilityBill) {
            return;
        }

        $rows = [];

        foreach ($children as $child) {
            $label = $child['label'] ?? null;
            $amount = $child['amount'] ?? null;

            $rows[] = [
                'label' => is_string($label) ? $label : null,
                'amount' => is_string($amount) || is_int($amount) || is_float($amount) ? $amount : null,
            ];
        }

        resolve(SaveBillLines::class)->handle($record, $rows);
    }
}
