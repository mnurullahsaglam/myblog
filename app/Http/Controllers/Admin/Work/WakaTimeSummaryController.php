<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Work;

use App\Http\Controllers\Controller;
use App\Models\WakaTimeSummary;
use App\Tables\Definitions\WakaTimeSummaryEntryTable;
use App\Tables\Definitions\WakaTimeSummaryTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only: WakaTime data is synced from the API and never authored by hand,
 * so this does not extend AdminResourceController.
 */
final class WakaTimeSummaryController extends Controller
{
    public function index(Request $request): Response
    {
        $table = new WakaTimeSummaryTable;

        return Inertia::render('Work/WakaTimeSummaries/Index', [
            'schema' => $table->schema(),
            'rows' => fn (): mixed => $table->rows($request),
        ]);
    }

    public function show(Request $request, WakaTimeSummary $wakaTimeSummary): Response
    {
        $entries = new WakaTimeSummaryEntryTable($wakaTimeSummary->id);
        $seconds = (int) $wakaTimeSummary->total_seconds;

        return Inertia::render('Work/WakaTimeSummaries/Show', [
            'summary' => [
                'id' => $wakaTimeSummary->id,
                'date' => $wakaTimeSummary->date->toDateString(),
                'totalSeconds' => $seconds,
                'duration' => intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m',
                'entryCount' => $wakaTimeSummary->entries()->count(),
            ],
            'schema' => $entries->schema(),
            'rows' => fn (): mixed => $entries->rows($request),
        ]);
    }
}
