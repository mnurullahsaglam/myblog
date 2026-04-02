<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WakaTimeService
{
    private string $baseUrl = 'https://api.wakatime.com/api/v1';

    private string $appId;

    private string $appSecret;

    public function __construct()
    {
        $this->appId = config('services.wakatime.app_id');
        $this->appSecret = config('services.wakatime.app_secret');
    }

    public function authenticate()
    {
        $url = 'https://wakatime.com/oauth/authorize';

        $response = Http::asForm()->post($url, [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'grant_type' => 'token',
        ]);

        dd($response->json(), $response->status(), $response);
    }
}

