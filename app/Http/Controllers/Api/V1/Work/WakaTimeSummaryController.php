<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WakaTimeSummaryResource;
use App\Models\WakaTimeSummary;
use App\Tables\Definitions\WakaTimeSummaryTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only, for the same reason the panel's version is: WakaTime data is synced
 * from an API and never authored by hand.
 */
final class WakaTimeSummaryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return WakaTimeSummaryResource::collection((new WakaTimeSummaryTable)->records($request));
    }

    public function show(WakaTimeSummary $wakaTimeSummary): WakaTimeSummaryResource
    {
        return new WakaTimeSummaryResource($wakaTimeSummary);
    }
}
