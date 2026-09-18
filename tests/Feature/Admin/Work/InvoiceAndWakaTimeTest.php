<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('local');
});

// ------------------------------------------------------------------ Invoices

it('lists invoices with the client title', function (): void {
    $client = Client::factory()->create(['title' => 'Acme']);
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_number' => 'INV-001']);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            $page->component('Work/Invoices/Index');

            $cells = $page->toArray()['props']['rows']['data'][0]['cells'];

            expect($cells['invoice_number']['display'])->toBe('INV-001');
            expect($cells['client.title']['display'])->toBe('Acme');
        });
});

it('formats invoice totals with the invoice currency', function (): void {
    Invoice::factory()->create(['total_amount' => 12000, 'currency' => 'EUR']);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.data.0.cells.total_amount.display', '€12,000.00')
        );
});

it('summarises invoices in tiles', function (): void {
    Invoice::factory()->create(['currency' => 'TRY', 'amount' => 1000, 'tax_rate' => 20, 'tax_amount' => 200, 'total_amount' => 1200]);
    Invoice::factory()->create(['currency' => 'TRY', 'amount' => 2000, 'tax_rate' => 20, 'tax_amount' => 400, 'total_amount' => 2400]);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tiles', 4)
            ->where('tiles.0.value', '₺3,600.00')
            ->where('tiles.1.value', '₺600.00')
            ->where('tiles.2.value', '2')
            ->where('tiles.3.value', '₺1,800.00')
        );
});

it('recomputes tax and total on the server', function (): void {
    $client = Client::factory()->create();

    $this->post(route('admin.invoices.store'), [
        'client_id' => $client->id,
        'invoice_number' => 'INV-100',
        'issued_at' => '2026-05-01 10:00:00',
        'currency' => 'TRY',
        'amount' => 1000,
        'tax_rate' => 20,
        // Deliberately wrong; the server must ignore these.
        'tax_amount' => 9999,
        'total_amount' => 1,
        'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
    ])->assertRedirect(route('admin.invoices.index'));

    $invoice = Invoice::where('invoice_number', 'INV-100')->firstOrFail();

    expect($invoice->tax_amount)->toBe(200);
    expect($invoice->total_amount)->toBe(1200);
});

it('stores the invoice archive privately', function (): void {
    $client = Client::factory()->create();

    $this->post(route('admin.invoices.store'), [
        'client_id' => $client->id,
        'invoice_number' => 'INV-101',
        'issued_at' => '2026-05-01 10:00:00',
        'currency' => 'TRY',
        'amount' => 100,
        'tax_rate' => 0,
        'invoice' => UploadedFile::fake()->create('invoice.zip', 10, 'application/zip'),
    ]);

    $invoice = Invoice::where('invoice_number', 'INV-101')->firstOrFail();

    Storage::disk('local')->assertExists($invoice->invoice);
    Storage::disk('public')->assertMissing($invoice->invoice);
});

it('requires an archive on create but not on update', function (): void {
    $client = Client::factory()->create();

    $this->from(route('admin.invoices.create'))
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'INV-102',
            'issued_at' => '2026-05-01 10:00:00',
            'currency' => 'TRY',
            'amount' => 100,
            'tax_rate' => 0,
        ])
        ->assertSessionHasErrors('invoice');

    $invoice = Invoice::factory()->create(['invoice' => 'invoices/existing.zip', 'amount' => 100, 'tax_rate' => 0]);

    $this->put(route('admin.invoices.update', $invoice), [
        'client_id' => $invoice->client_id,
        'invoice_number' => $invoice->invoice_number,
        'issued_at' => '2026-06-01 09:00:00',
        'currency' => $invoice->currency->value,
        'amount' => 200,
        'tax_rate' => 10,
    ])->assertSessionHasNoErrors();

    $invoice->refresh();

    expect($invoice->invoice)->toBe('invoices/existing.zip');
    expect($invoice->total_amount)->toBe(220);
});

it('rejects a duplicate invoice number and a non-zip upload', function (): void {
    $existing = Invoice::factory()->create();
    $client = Client::factory()->create();

    $this->from(route('admin.invoices.create'))
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => $existing->invoice_number,
            'issued_at' => '2026-05-01 10:00:00',
            'currency' => 'TRY',
            'amount' => 100,
            'tax_rate' => 150,
            'invoice' => UploadedFile::fake()->image('not-an-archive.png'),
        ])
        ->assertSessionHasErrors(['invoice_number', 'tax_rate', 'invoice']);
});

// -------------------------------------------------------- WakaTime summaries

it('lists summaries newest first with a human duration', function (): void {
    WakaTimeSummary::factory()->create(['date' => '2026-01-01', 'total_seconds' => 100]);
    $newest = WakaTimeSummary::factory()->create(['date' => '2026-06-01', 'total_seconds' => 5400]);

    $this->get(route('admin.waka-time-summaries.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/WakaTimeSummaries/Index')
            ->where('rows.data.0.id', $newest->id)
            ->where('rows.data.0.cells.duration.display', '1h 30m')
        );
});

it('shows a summary with its entries', function (): void {
    $summary = WakaTimeSummary::factory()->create(['total_seconds' => 3600]);
    WakaTimeSummaryEntry::factory()->count(3)->create(['waka_time_summary_id' => $summary->id]);

    $this->get(route('admin.waka-time-summaries.show', $summary))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/WakaTimeSummaries/Show')
            ->where('summary.duration', '1h 0m')
            ->where('summary.entryCount', 3)
            ->has('rows.data', 3)
        );
});

it('only shows entries belonging to that summary', function (): void {
    $summary = WakaTimeSummary::factory()->create();
    $other = WakaTimeSummary::factory()->create();

    WakaTimeSummaryEntry::factory()->count(2)->create(['waka_time_summary_id' => $summary->id]);
    WakaTimeSummaryEntry::factory()->count(5)->create(['waka_time_summary_id' => $other->id]);

    $this->get(route('admin.waka-time-summaries.show', $summary))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 2));
});

it('filters summary entries by type', function (): void {
    $summary = WakaTimeSummary::factory()->create();
    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
    ]);
    WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => WakaTimeSummaryEntry::TYPE_EDITOR,
    ]);

    $this->get(route('admin.waka-time-summaries.show', [
        'wakaTimeSummary' => $summary,
        'filter' => ['type' => [WakaTimeSummaryEntry::TYPE_LANGUAGE]],
    ]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
});

it('exposes no write routes for summaries', function (string $name): void {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.waka-time-summaries.create',
    'admin.waka-time-summaries.store',
    'admin.waka-time-summaries.edit',
    'admin.waka-time-summaries.update',
    'admin.waka-time-summaries.destroy',
]);

it('lists invoices and summaries in the work cluster', function (): void {
    $work = collect(App\Support\Navigation::clusters())->firstWhere('label', 'Work');

    expect(collect($work['items'])->pluck('route'))
        ->toContain('admin.invoices.index', 'admin.waka-time-summaries.index');
});
