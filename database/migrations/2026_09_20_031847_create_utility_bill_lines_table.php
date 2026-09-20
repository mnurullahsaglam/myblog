<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The breakdown a bill prints: enerji bedeli, dağıtım bedeli, KDV, ÖİV.
 *
 * Labels and amounts are stored verbatim rather than derived, so a 2026 bill
 * still shows 2026's figures after the next rate change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            // Signed: bills carry discounts and previous-balance credits.
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bill_lines');
    }
};
