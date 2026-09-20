<?php

declare(strict_types=1);

use App\Enums\Currencies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('currency', array_column(Currencies::cases(), 'value'))->default('TRY');
            $table->text('description');
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_tax_deductible')->default(false);
            $table->string('receipt_path')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index('is_recurring');
            $table->index('is_tax_deductible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
