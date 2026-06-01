<?php

namespace App\Http\Controllers;

use App\Services\WakaTimeService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WakaTimeOAuthController extends Controller
{
    public function __construct(private readonly WakaTimeService $wakatime) {}

    /**
     * Kick off the OAuth consent flow: store an anti-CSRF state and redirect to WakaTime.
     */
    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('wakatime_oauth_state', $state);

        return redirect()->away($this->wakatime->getAuthorizationUrl($state));
    }

    /**
     * Handle WakaTime's redirect back: validate state, exchange the code, store tokens.
     */
    public function callback(Request $request): RedirectResponse
    {
        $panel = filament()->getDefaultPanel()->getUrl();

        if ($request->filled('error')) {
            return $this->back($panel, false, 'WakaTime authorization was denied: ' . $request->string('error'));
        }

        $expectedState = $request->session()->pull('wakatime_oauth_state');

        if (! $request->filled('state') || ! $request->filled('code') || ! hash_equals((string) $expectedState, $request->string('state')->toString())) {
            return $this->back($panel, false, 'Invalid OAuth state or missing code. Please try connecting again.');
        }

        try {
            $this->wakatime->exchangeCodeForToken($request->string('code')->toString());
        } catch (\Throwable $e) {
            report($e);

            return $this->back($panel, false, 'Failed to connect WakaTime: ' . $e->getMessage());
        }

        return $this->back($panel, true, 'WakaTime connected successfully. Your daily sync is now active.');
    }

    private function back(string $url, bool $success, string $message): RedirectResponse
    {
        Notification::make()
            ->title($success ? 'WakaTime connected' : 'WakaTime connection failed')
            ->body($message)
            ->{$success ? 'success' : 'danger'}()
            ->send();

        return redirect()->to($url);
    }
}
