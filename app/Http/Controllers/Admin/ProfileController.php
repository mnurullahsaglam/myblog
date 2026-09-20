<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\NotifiesAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreferencesRequest;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

final class ProfileController extends Controller
{
    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function index(Request $request): Response
    {
        $user = $request->user()?->fresh();

        $appearance = Appearance::forUser($user);

        return Inertia::render('Profile', [
            'appearance' => $appearance,
            'accents' => array_map(
                fn (string $name): array => ['value' => $name, 'label' => ucfirst($name)],
                AccentRamps::names(),
            ),
            'schemes' => array_map(
                fn (string $scheme): array => ['value' => $scheme, 'label' => ucfirst($scheme)],
                Appearance::SCHEMES,
            ),
            'twoFactorEnabled' => $user?->hasEnabledTwoFactorAuthentication() ?? false,
            'twoFactorPending' => $user !== null
                && $user->two_factor_secret !== null
                && $user->two_factor_confirmed_at === null,

            'passkeys' => $user === null ? [] : $user->passkeys()
                ->latest()
                ->get()
                ->map(fn (Passkey $passkey): array => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'createdAt' => $passkey->created_at?->diffForHumans(),
                    'lastUsedAt' => $passkey->last_used_at?->diffForHumans(),
                ])
                ->all(),
        ]);
    }

    public function updatePreferences(PreferencesRequest $request): RedirectResponse
    {
        /** @var array{accent: string|null, color_scheme: string|null} $data */
        $data = $request->validated();

        $user = $request->user();

        abort_if($user === null, 401);

        $user->update(['preferences' => array_merge($user->preferences ?? [], $data)]);

        $this->notifier->success('Appearance saved');

        return to_route('admin.profile');
    }
}
