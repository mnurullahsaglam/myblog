<?php

declare(strict_types=1);

use App\Enums\Currencies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bill against one account.
 *
 * No tax rate and no levy columns: the breakdown lives in utility_bill_lines
 * exactly as the bill prints it, because rates and levies change and a stored
 * rule would silently recompute old bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utility_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

            $table->string('bill_number')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('due_date');

            $table->decimal('meter_start', 12, 3)->nullable();
            $table->decimal('meter_end', 12, 3)->nullable();

            $table->decimal('total_amount', 15, 2);
            $table->enum('currency', array_column(Currencies::cases(), 'value'))->default('TRY');
            $table->timestamp('paid_at')->nullable();
            $table->string('document_path')->nullable();

            $table->timestamps();

            $table->index('due_date');
            $table->index(['utility_account_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
