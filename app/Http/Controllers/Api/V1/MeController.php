<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Ability;
use App\Enums\Area;
use App\Http\Controllers\Controller;
use App\Support\Access\AccessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $profile = resolve(AccessProfile::class);

        return response()->json([
            'data' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
                'email' => $user->email,
                'areas' => array_map(fn (Area $area): string => $area->value, $profile->areas()),
                'abilities' => array_map(
                    fn (Ability $ability): string => $ability->value,
                    array_filter(Ability::cases(), $profile->allows(...)),
                ),
            ],
        ]);
    }
}
