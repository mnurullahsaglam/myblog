<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\NotifiesAdmin;
use App\Services\WakaTimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class WakaTimeOAuthController extends Controller
{
    public function __construct(private readonly WakaTimeService $wakatime) {}

    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('wakatime_oauth_state', $state);

        return redirect()->away($this->wakatime->getAuthorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $panel = route('admin.coding-dashboard');

        if ($request->filled('error')) {
            return $this->back($panel, false, 'WakaTime authorization was denied: '.$request->string('error'));
        }

        $expectedState = $request->session()->pull('wakatime_oauth_state');
        $expectedState = is_string($expectedState) ? $expectedState : '';

        if (! $request->filled('state') || ! $request->filled('code') || ! hash_equals($expectedState, $request->string('state')->toString())) {
            return $this->back($panel, false, 'Invalid OAuth state or missing code. Please try connecting again.');
        }

        try {
            $this->wakatime->exchangeCodeForToken($request->string('code')->toString());
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->back($panel, false, 'Failed to connect WakaTime: '.$throwable->getMessage());
        }

        return $this->back($panel, true, 'WakaTime connected successfully. Your daily sync is now active.');
    }

    private function back(string $url, bool $success, string $message): RedirectResponse
    {
        $notifier = resolve(NotifiesAdmin::class);
        $title = $success ? 'WakaTime connected' : 'WakaTime connection failed';

        $success
            ? $notifier->success($title, $message)
            : $notifier->danger($title, $message);

        return redirect()->to($url);
    }
}
