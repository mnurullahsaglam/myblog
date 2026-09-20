<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('client_id')->constrained('clients');
            $table->string('invoice_number')->unique();
            $table->date('issued_at');
            $table->integer('tax_rate');
            $table->integer('tax_amount');
            $table->integer('amount');
            $table->integer('total_amount');
            $table->string('currency');

            $table->string('invoice');

            $table->timestamps();
        });
    }
};
