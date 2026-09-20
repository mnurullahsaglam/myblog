<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Forms\Definitions\IncomeForm;
use App\Models\Client;
use App\Models\Income;
use App\Models\User;
use App\Support\Access\AccessProfile;
use App\Tables\Definitions\IncomeTable;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

function asProfile(User $user): void
{
    app()->instance(AccessProfile::class, AccessProfile::forUser($user));
}

it('keeps a hidden column out of the schema', function (): void {
    asProfile($this->member);

    expect(array_column((new IncomeTable)->schema()['columns'], 'key'))->not->toContain('client.title');
});

it('keeps it in the schema for the admin', function (): void {
    asProfile($this->owner);

    expect(array_column((new IncomeTable)->schema()['columns'], 'key'))->toContain('client.title');
});

it('never serialises the hidden value into the payload', function (): void {
    $client = Client::factory()->create(['title' => 'Zzsecret Client']);
    Income::factory()->create(['client_id' => $client->id]);

    $response = $this->actingAs($this->member)->get(route('admin.incomes.index'));

    expect($response->getContent())->not->toContain('Zzsecret Client');
});

it('does serialise it for the admin', function (): void {
    $client = Client::factory()->create(['title' => 'Zzsecret Client']);
    Income::factory()->create(['client_id' => $client->id]);

    expect($this->actingAs($this->owner)->get(route('admin.incomes.index'))->getContent())
        ->toContain('Zzsecret Client');
});

it('keeps a hidden filter out of the schema', function (): void {
    asProfile($this->member);

    expect(array_column((new IncomeTable)->schema()['filters'], 'key'))->not->toContain('client_id');
});

it('ignores a hidden filter applied by hand', function (): void {
    $client = Client::factory()->create();
    Income::factory()->count(2)->create(['client_id' => $client->id]);
    Income::factory()->count(3)->create(['client_id' => null]);

    asProfile($this->member);

    $filtered = (new IncomeTable)->rows(Request::create('/', 'GET', ['filter' => ['client_id' => [$client->id]]]));

    expect($filtered->total())->toBe(5, 'the hidden filter narrowed the result');
});

it('honours the filter for the admin', function (): void {
    $client = Client::factory()->create();
    Income::factory()->count(2)->create(['client_id' => $client->id]);
    Income::factory()->count(3)->create(['client_id' => null]);

    asProfile($this->owner);

    expect((new IncomeTable)->rows(Request::create('/', 'GET', ['filter' => ['client_id' => [$client->id]]]))->total())
        ->toBe(2);
});

it('ignores sorting by a hidden column applied by hand', function (): void {
    Income::factory()->count(3)->create();

    asProfile($this->member);

    expect((new IncomeTable)->rows(Request::create('/', 'GET', ['sort' => 'client.title']))->total())->toBe(3);
});

it('keeps a hidden field out of the form schema and values', function (): void {
    asProfile($this->member);

    $income = Income::factory()->create();
    $form = new IncomeForm;

    expect(array_column($form->schema()['fields'], 'key'))->not->toContain('client_id')
        ->and($form->values($income))->not->toHaveKey('client_id');
});

it('keeps a hidden field out of the bulk editable whitelist', function (): void {
    asProfile($this->member);

    expect((new IncomeForm)->bulkEditableFields())->not->toContain('client_id');
});

it('prohibits a bulk value for a hidden field', function (): void {
    asProfile($this->member);

    expect((new IncomeForm)->bulkValueRules('client_id')['value'])->toContain('prohibited');
});

it('refuses a bulk edit of the hidden field over HTTP', function (): void {
    $incomes = Income::factory()->count(2)->create(['client_id' => null]);
    $client = Client::factory()->create();

    $this->actingAs($this->member)
        ->from(route('admin.incomes.index'))
        ->patch(route('admin.incomes.bulk-update'), [
            'ids' => $incomes->modelKeys(),
            'field' => 'client_id',
            'value' => $client->id,
        ])
        ->assertSessionHasErrors('field');

    expect(Income::whereKey($incomes->modelKeys())->pluck('client_id')->all())->each->toBeNull();
});

it('ignores a client id posted by her', function (): void {
    $client = Client::factory()->create();

    $this->actingAs($this->member)
        ->post(route('admin.incomes.store'), [
            'amount' => 100,
            'currency' => 'TRY',
            'date' => now()->toDateString(),
            'description' => 'Mine',
            'client_id' => $client->id,
        ])
        ->assertRedirect();

    expect(Income::query()->where('description', 'Mine')->sole()->client_id)->toBeNull();
});

it('covers every ability', function (Ability $ability): void {
    expect(AccessProfile::forUser($this->owner)->allows($ability))->toBeTrue()
        ->and(AccessProfile::forUser($this->member)->allows($ability))->toBeFalse();
})->with(fn (): array => array_map(fn (Ability $a): array => [$a], Ability::cases()));

it('degrades the derived source label rather than naming the client', function (): void {
    $client = Client::factory()->create(['title' => 'Zzderived Client']);
    $income = Income::factory()->create(['client_id' => $client->id]);

    asProfile($this->member);
    expect($income->fresh()->source)->toBe('Client work');

    asProfile($this->owner);
    expect($income->fresh()->source)->toBe('Zzderived Client');
});

it('leaves the source label alone when there is no client', function (): void {
    $income = Income::factory()->create(['client_id' => null]);

    asProfile($this->member);

    expect($income->fresh()->source)->toBe('Other');
});

it('keeps the derived label out of the form values too', function (): void {
    $client = Client::factory()->create(['title' => 'Zzderived Client']);
    $income = Income::factory()->create(['client_id' => $client->id]);

    asProfile($this->member);

    expect((new IncomeForm)->values($income->fresh()))->not->toContain('Zzderived Client');
});

it('does not name the client on the show page', function (): void {
    $client = Client::factory()->create(['title' => 'Zzderived Client']);
    $income = Income::factory()->create(['client_id' => $client->id]);

    expect($this->actingAs($this->member)->get(route('admin.incomes.show', $income))->getContent())
        ->not->toContain('Zzderived Client');
});
