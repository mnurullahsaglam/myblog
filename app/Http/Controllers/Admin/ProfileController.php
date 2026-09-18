<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // fresh() so every column is hydrated: Model::shouldBeStrict() throws on
        // an attribute that was never selected, which a partially built user has.
        $user = $request->user()?->fresh();

        return Inertia::render('Profile', [
            'twoFactorEnabled' => $user?->hasEnabledTwoFactorAuthentication() ?? false,
            'twoFactorPending' => $user !== null
                && $user->two_factor_secret !== null
                && $user->two_factor_confirmed_at === null,
        ]);
    }
}
