<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invite;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\Publisher;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Models\WakaTimeSummary;
use App\Models\Writer;
use App\Notifications\AdminAlert;
use Illuminate\Database\Eloquent\Model;
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

/**
 * A record for each route parameter, so the matrix does not collapse into
 * special cases for routes that bind a model.
 *
 * Models are returned whole rather than as keys: several of them resolve their
 * route key to a slug, so a raw id would generate a URL that binding cannot
 * resolve and the matrix would read a 404 as a denial.
 *
 * The notification routes bind to the signed-in user's own notifications, so
 * that one is created against $user; everything else is standalone.
 */
function parameterValue(string $parameter, User $user): Model|string|int
{
    return match ($parameter) {
        'post' => Post::factory()->create(),
        'category' => Category::factory()->create(),
        'invite' => Invite::factory()->create(),
        'device' => User::factory()->create()->createToken('matrix')->accessToken->getKey(),
        'publisher' => Publisher::factory()->create(),
        'writer' => Writer::factory()->create(),
        'book' => Book::factory()->create(),
        'utility_account' => UtilityAccount::factory()->create(),
        'utility_bill', 'utilityBill' => UtilityBill::factory()->create(),
        'client' => Client::factory()->create(),
        'project' => Project::factory()->create(),
        'repository' => Repository::factory()->create(),
        'invoice' => Invoice::factory()->create(),
        'task' => Task::factory()->create(),
        'wakaTimeSummary' => WakaTimeSummary::factory()->create(),
        'income' => Income::factory()->create(),
        'expense' => Expense::factory()->create(),
        'debt' => Debt::factory()->create(),
        'notification' => (function () use ($user): string {
            $user->notify(new AdminAlert('Matrix'));

            return (string) $user->unreadNotifications()->sole()->getKey();
        })(),
        'resource' => 'books',
        default => '1',
    };
}

/**
 * @param  array<int, string>  $names
 * @return array<string, Model|string|int>
 */
function routeParameters(array $names, User $user): array
{
    $parameters = [];

    foreach ($names as $name) {
        $parameters[$name] = parameterValue($name, $user);
    }

    return $parameters;
}
