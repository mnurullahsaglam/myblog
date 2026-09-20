<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Ability;
use App\Enums\Area;
use App\Http\Controllers\Controller;
use App\Support\Access\AccessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the token belongs to, and what it reaches.
 *
 * A client needs this to build its navigation: without it the only way to learn
 * which areas a person has is to try each one and watch for 404s, which is six
 * requests and a guess. It reveals nothing to somebody already holding the
 * token, since they could probe those endpoints themselves.
 */
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
