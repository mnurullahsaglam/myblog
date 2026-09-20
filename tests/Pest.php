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

function userWithoutRole(string $email): User
{
    $user = User::factory()->create(['email' => $email]);

    DB::table('users')->where('id', $user->getKey())->update(['role' => '']);

    return $user->fresh() ?? $user;
}

function apiAs(User $user): PendingApiRequest
{
    resolve('auth')->forgetGuards();

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
