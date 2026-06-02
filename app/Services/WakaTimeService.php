<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WakaTimeService
{
    private const BASE_URL = 'https://api.wakatime.com/api/v1';

    private const AUTHORIZE_URL = 'https://wakatime.com/oauth/authorize';

    private const TOKEN_URL = 'https://wakatime.com/oauth/token';

    private const SETTING_GROUP = 'wakatime';

    /** Scope required to read summaries (incl. project/language/editor/os/category breakdowns). */
    public const SCOPE = 'read_summaries';

    private string $appId;

    private string $appSecret;

    private string $redirectUri;

    public function __construct()
    {
        $this->appId = self::stringConfig('services.wakatime.app_id');
        $this->appSecret = self::stringConfig('services.wakatime.app_secret');
        $this->redirectUri = self::stringConfig('services.wakatime.redirect');
    }

    /**
     * Build the consent URL the user is redirected to (one-time bootstrap).
     */
    public function getAuthorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->appId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code from the callback for tokens and persist them.
     */
    public function exchangeCodeForToken(string $code): void
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WakaTime token exchange failed: {$response->status()} - {$response->body()}");
        }

        $this->storeTokens(self::jsonArray($response->json()));
    }

    /**
     * Return a valid access token, refreshing first if it is expired or about to expire.
     */
    public function getValidAccessToken(): string
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('WakaTime is not connected. Visit the admin panel and click "Connect WakaTime".');
        }

        $expiresAt = $this->getExpiresAt();

        if ($expiresAt === null || $expiresAt->subMinutes(5)->isPast()) {
            $this->refreshToken();
        }

        return $this->decrypt($this->setting('access_token'));
    }

    /**
     * Refresh the access token using the stored refresh token.
     */
    public function refreshToken(): void
    {
        $refreshToken = $this->decrypt($this->setting('refresh_token'));

        if ($refreshToken === '') {
            throw new RuntimeException('No WakaTime refresh token stored. Reconnect required.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WakaTime token refresh failed: {$response->status()} - {$response->body()}. Reconnect required.");
        }

        $this->storeTokens(self::jsonArray($response->json()));
    }

    /**
     * Fetch daily summaries between two dates (inclusive). Returns the API "data" array,
     * one element per day, each containing grand_total + breakdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSummaries(CarbonInterface $start, CarbonInterface $end): array
    {
        $response = Http::withToken($this->getValidAccessToken())
            ->acceptJson()
            ->get(self::BASE_URL.'/users/current/summaries', [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("WakaTime summaries request failed: {$response->status()} - {$response->body()}");
        }

        $data = $response->json('data', []);

        if (! is_array($data)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $days */
        $days = array_values(array_filter($data, 'is_array'));

        return $days;
    }

    public function isConnected(): bool
    {
        return $this->decrypt($this->setting('refresh_token')) !== '';
    }

    public function disconnect(): void
    {
        // Delete model instances (not a bulk query) so the Setting model's `deleted`
        // event fires and the cached values are forgotten.
        Setting::where('group', self::SETTING_GROUP)
            ->whereIn('name', ['access_token', 'refresh_token', 'expires_at'])
            ->get()
            ->each
            ->delete();
    }

    /**
     * Persist the token payload returned by WakaTime's token endpoint.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeTokens(array $payload): void
    {
        $accessToken = $payload['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('WakaTime token response did not contain an access_token: '.json_encode($payload));
        }

        Setting::set(self::SETTING_GROUP, 'access_token', Crypt::encryptString($accessToken));

        $refreshToken = $payload['refresh_token'] ?? null;

        if (is_string($refreshToken) && $refreshToken !== '') {
            Setting::set(self::SETTING_GROUP, 'refresh_token', Crypt::encryptString($refreshToken));
        }

        // WakaTime returns expires_at as an ISO-8601 timestamp; fall back to expires_in seconds.
        $expiresAtRaw = $payload['expires_at'] ?? null;
        $expiresInRaw = $payload['expires_in'] ?? null;

        $expiresAt = match (true) {
            is_string($expiresAtRaw) || is_int($expiresAtRaw) => Carbon::parse($expiresAtRaw),
            is_numeric($expiresInRaw) => now()->addSeconds((int) $expiresInRaw),
            default => null,
        };

        if ($expiresAt !== null) {
            Setting::set(self::SETTING_GROUP, 'expires_at', $expiresAt->toIso8601String());
        }
    }

    private function getExpiresAt(): ?Carbon
    {
        $value = $this->setting('expires_at');

        return ($value !== null && $value !== '') ? Carbon::parse($value) : null;
    }

    /**
     * Read a stored WakaTime setting as a string (or null when absent/non-scalar).
     */
    private function setting(string $name): ?string
    {
        $value = Setting::get(self::SETTING_GROUP, $name);

        return is_string($value) ? $value : null;
    }

    private static function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Coerce a decoded JSON payload to a string-keyed array.
     *
     * @return array<string, mixed>
     */
    private static function jsonArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    private function decrypt(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return '';
        }
    }
}
