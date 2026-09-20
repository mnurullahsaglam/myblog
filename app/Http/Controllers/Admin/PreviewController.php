<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\Access\AccessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PreviewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->abilities() !== [], 404);
        abort_if(resolve(AccessProfile::class)->isPreviewing(), 403);

        $role = UserRole::tryFrom($request->string('role')->toString());

        abort_if(! $role instanceof UserRole, 422);
        abort_if(count($role->areas()) >= count($user->areas()), 403);

        $request->session()->put('preview_role', $role->value);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('preview_role');

        return back();
    }
}
