<?php

declare(strict_types=1);

namespace App\Actions\Library;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetch a cover from Open Library and store it like a manual upload.
 *
 * default=false is not optional: without it a missing cover comes back as
 * HTTP 200 carrying a blank placeholder, and the library fills up with empty
 * images that look like successful downloads.
 */
final class DownloadCover
{
    private const string BASE_URL = 'https://covers.openlibrary.org/b/id/';

    private const int MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return string|null the stored path on the public disk
     */
    public function handle(int $coverId): ?string
    {
        try {
            $response = Http::withUserAgent('myblog/1.0 ('.config()->string('services.open_library.contact').')')
                ->timeout(10)
                ->withOptions(['allow_redirects' => true])
                ->get(self::BASE_URL.$coverId.'-L.jpg', ['default' => 'false']);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $path = 'books/'.Str::uuid()->toString().'.jpg';

        Storage::disk('public')->put($path, $body);

        return $path;
    }
}
