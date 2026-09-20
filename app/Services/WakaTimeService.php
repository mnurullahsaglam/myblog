<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final readonly class WakaTimeService
{
    private const string BASE_URL = 'https://api.wakatime.com/api/v1';

    private const string AUTHORIZE_URL = 'https://wakatime.com/oauth/authorize';

    private const string TOKEN_URL = 'https://wakatime.com/oauth/token';

    private const string SETTING_GROUP = 'wakatime';

    public const string SCOPE = 'read_summaries';

    private string $appId;

    private string $appSecret;

    private string $redirectUri;

    public function __construct()
    {
        $this->appId = $this->stringConfig('services.wakatime.app_id');
        $this->appSecret = $this->stringConfig('services.wakatime.app_secret');
        $this->redirectUri = $this->stringConfig('services.wakatime.redirect');
    }

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

        $this->storeTokens($this->jsonArray($response->json()));
    }

    public function getValidAccessToken(): string
    {
        throw_unless($this->isConnected(), RuntimeException::class, 'WakaTime is not connected. Visit the admin panel and click "Connect WakaTime".');

        $expiresAt = $this->getExpiresAt();

        if (! $expiresAt instanceof Carbon || $expiresAt->subMinutes(5)->isPast()) {
            $this->refreshToken();
        }

        return $this->decrypt($this->setting('access_token'));
    }

    public function refreshToken(): void
    {
        $refreshToken = $this->decrypt($this->setting('refresh_token'));

        throw_if($refreshToken === '', RuntimeException::class, 'No WakaTime refresh token stored. Reconnect required.');

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

        $this->storeTokens($this->jsonArray($response->json()));
    }

    /**
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
        $days = array_values(array_filter($data, is_array(...)));

        return $days;
    }

    public function isConnected(): bool
    {
        return $this->decrypt($this->setting('refresh_token')) !== '';
    }

    public function disconnect(): void
    {
        Setting::where('group', self::SETTING_GROUP)
            ->whereIn('name', ['access_token', 'refresh_token', 'expires_at'])
            ->get()
            ->each
            ->delete();
    }

    /**
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

        $expiresAtRaw = $payload['expires_at'] ?? null;
        $expiresInRaw = $payload['expires_in'] ?? null;

        $expiresAt = match (true) {
            is_string($expiresAtRaw) || is_int($expiresAtRaw) => Date::parse($expiresAtRaw),
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

        return ($value !== null && $value !== '') ? Date::parse($value) : null;
    }

    private function setting(string $name): ?string
    {
        $value = Setting::get(self::SETTING_GROUP, $name);

        return is_string($value) ? $value : null;
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $value): array
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
