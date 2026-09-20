<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
use Override;

/**
 * Remove keys past their window.
 *
 * A key is only useful while a phone might still retry the write it belongs to.
 *
 * Deleted through the models in chunks rather than with one query-builder
 * delete. IdempotencyKey has no events and no pivot to orphan, so a bulk delete
 * would be safe here — but BulkWriteSafetyTest forbids the pattern outright, and
 * a rule with exemptions is a rule nobody trusts. The table is pruned daily in a
 * two-person household; the loop costs nothing worth having.
 */
final class PruneIdempotencyKeys extends Command
{
    #[Override]
    protected $signature = 'idempotency:prune';

    #[Override]
    protected $description = 'Delete idempotency keys that have expired';

    public function handle(): int
    {
        $deleted = 0;

        IdempotencyKey::query()
            ->where('expires_at', '<=', now())
            ->lazyById(500)
            ->each(function (IdempotencyKey $key) use (&$deleted): void {
                $key->delete();
                $deleted++;
            });

        $this->info($deleted.' expired idempotency keys removed.');

        return self::SUCCESS;
    }
}
