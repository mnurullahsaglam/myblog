<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
use Override;

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
