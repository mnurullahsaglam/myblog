<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes schema left behind by things the application never used.
 *
 * panphp/pan is uninstalled: nothing ever called Pan::, the table held no rows,
 * and the package registered a public endpoint for events that were never
 * recorded. The invoices.invoice_pdf column was never written or read - it
 * belonged to an invoice PDF feature that was not built.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pan_analytics');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('invoice_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('invoice_pdf')->nullable();
        });
    }
};
