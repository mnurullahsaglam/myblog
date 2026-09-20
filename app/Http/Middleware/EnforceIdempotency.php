<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replay a stored response for a repeated Idempotency-Key.
 *
 * The phone queues writes while offline, so a retry after a dropped connection
 * must not record the expense twice. Keys are scoped to the user, so one account
 * cannot read back a response that was never theirs, and a key replayed against
 * a different payload is refused rather than answered: silently returning the
 * answer to a different question is worse than refusing.
 *
 * The body is stored as raw text rather than as jsonb, because PostgreSQL's
 * jsonb normalises and reorders keys — a replay would then differ from the
 * response it is supposed to repeat.
 */
final class EnforceIdempotency
{
    private const int HOURS = 24;

    /**
     * @var array<int, string>
     */
    private const array SAFE = ['GET', 'HEAD', 'OPTIONS'];

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
        $hash = hash('sha256', $endpoint.'|'.json_encode($request->all()));

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
