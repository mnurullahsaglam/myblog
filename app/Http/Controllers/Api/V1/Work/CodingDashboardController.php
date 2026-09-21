<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Models\WakaTimeSummary;
use App\Support\WakaTime\DashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;

/**
 * The panel's coding dashboard, for the phone.
 *
 * The aggregation is not repeated here. DashboardData produces what
 * CodingDashboard.vue draws, so both clients read one set of numbers and cannot
 * disagree about a total by counting it twice.
 */
final class CodingDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = DashboardData::for($request->string('range')->toString());

        $data['syncedAt'] = $this->syncedAt();
        $data['tiles'] = array_map(
            fn (array $tile): array => Arr::except($tile, 'icon'),
            $data['tiles'],
        );

        return response()->json(['data' => $data]);
    }

    /**
     * An instant rather than a phrase. When to call it "2 hours ago" belongs to
     * whoever is reading, in whatever locale they read in.
     */
    private function syncedAt(): ?string
    {
        $latest = WakaTimeSummary::query()->latest('updated_at')->first()?->updated_at;

        return $latest === null ? null : Date::instance($latest)->toIso8601String();
    }
}
