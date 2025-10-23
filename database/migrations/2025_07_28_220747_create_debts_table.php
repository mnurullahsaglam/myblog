<?php

use App\Enums\Currencies;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('creditor_name');
            $table->enum('creditor_type', ['person', 'institute'])->default('person');
            $table->decimal('amount', 15, 2);
            $table->enum('currency', array_column(Currencies::cases(), 'value'))->default('TRY');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->text('description')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
