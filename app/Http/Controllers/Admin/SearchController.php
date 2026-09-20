<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access\AccessProfile;
use App\Support\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return response()->json([
            'results' => GlobalSearch::query($request->string('q')->toString(), resolve(AccessProfile::class)),
        ]);
    }
}
