<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WakaTimeSummaryResource;
use App\Models\WakaTimeSummary;
use App\Tables\Definitions\WakaTimeSummaryTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WakaTimeSummaryController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'table' => (new WakaTimeSummaryTable)->schema(),
            'form' => null,
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return WakaTimeSummaryResource::collection((new WakaTimeSummaryTable)->records($request));
    }

    public function show(WakaTimeSummary $wakaTimeSummary): WakaTimeSummaryResource
    {
        return new WakaTimeSummaryResource($wakaTimeSummary);
    }
}
