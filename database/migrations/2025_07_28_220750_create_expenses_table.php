<?php

use App\Enums\Currencies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('currency', array_column(Currencies::cases(), 'value'))->default('TRY');
            $table->text('description');
            $table->string('receipt_path')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
