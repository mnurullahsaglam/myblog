<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets expenses be split into recurring subscriptions and tax-deductible
 * outgoings, which the ledger design surfaces as tabs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->boolean('is_recurring')->default(false)->after('description');
            $table->boolean('is_tax_deductible')->default(false)->after('is_recurring');

            $table->index('is_recurring');
            $table->index('is_tax_deductible');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['is_recurring']);
            $table->dropIndex(['is_tax_deductible']);
            $table->dropColumn(['is_recurring', 'is_tax_deductible']);
        });
    }
};
