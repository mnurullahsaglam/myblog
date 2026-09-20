<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/**
 * A user whose role the enum does not recognise.
 *
 * Not a state the application creates, but one a bad migration or a hand edit
 * can leave behind, and the panel must refuse it rather than guess. The column
 * is written directly because assigning an unknown value to an enum-cast
 * attribute throws before it ever reaches the database.
 */
function userWithoutRole(string $email): User
{
    $user = User::factory()->create(['email' => $email]);

    DB::table('users')->where('id', $user->getKey())->update(['role' => '']);

    return $user->fresh() ?? $user;
}

/**
 * Authenticate the next API request as this user, by token.
 *
 * The auth guard caches the user it resolved earlier in the same test, which
 * production never does because each request is its own process. Without
 * forgetting it first, a test that checks one user's payload against another's
 * silently checks the first user twice — and a leak test written that way passes
 * while leaking.
 */
function apiAs(User $user): PendingApiRequest
{
    app('auth')->forgetGuards();

    return new PendingApiRequest($user);
}

final readonly class PendingApiRequest
{
    public function __construct(private User $user) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function json(string $method, string $uri, array $data = []): TestResponse
    {
        return test()
            ->withToken($this->user->createToken('test')->plainTextToken)
            ->json($method, $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function get(string $uri): TestResponse
    {
        return $this->json('GET', $uri);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): TestResponse
    {
        return $this->json('POST', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): TestResponse
    {
        return $this->json('PUT', $uri, $data);
    }

    public function delete(string $uri): TestResponse
    {
        return $this->json('DELETE', $uri);
    }
}
