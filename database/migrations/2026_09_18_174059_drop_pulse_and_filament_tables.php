<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops tables belonging to packages that are no longer installed: Laravel
 * Pulse, and Filament's import and export bookkeeping.
 *
 * The notifications table stays; the notification bell reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'pulse_aggregates',
            'pulse_entries',
            'pulse_values',
            'failed_import_rows',
            'imports',
            'exports',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void {}
};
