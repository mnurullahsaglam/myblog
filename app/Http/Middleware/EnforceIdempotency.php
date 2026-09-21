<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class EnforceIdempotency
{
    private const int HOURS = 24;

    /**
     * @var array<int, string>
     */
    private const array SAFE = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(Request $request): array
    {
        $files = $request->allFiles();
        $input = [];

        foreach ($request->except(array_keys($files)) as $key => $value) {
            $input[(string) $key] = $value;
        }

        foreach ($files as $field => $file) {
            $input[(string) $field] = $file instanceof UploadedFile
                ? ['name' => $file->getClientOriginalName(), 'size' => $file->getSize()]
                : null;
        }

        return $input;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if (! is_string($key) || $key === '' || $user === null || in_array($request->method(), self::SAFE, true)) {
            return $next($request);
        }

        $endpoint = $request->method().' '.$request->path();
        $hash = hash('sha256', $endpoint.'|'.json_encode($this->fingerprint($request)));

        $existing = IdempotencyKey::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('key', $key)
            ->live()
            ->first();

        if ($existing instanceof IdempotencyKey) {
            abort_if($existing->payload_hash !== $hash, 422, 'That idempotency key was used for a different request.');

            return response($existing->response_body, $existing->response_status)
                ->header('Content-Type', 'application/json');
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            IdempotencyKey::query()->updateOrCreate(
                ['user_id' => $user->getAuthIdentifier(), 'key' => $key],
                [
                    'endpoint' => $endpoint,
                    'payload_hash' => $hash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => (string) $response->getContent(),
                    'expires_at' => now()->addHours(self::HOURS),
                ],
            );
        }

        return $response;
    }
}
