<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }
};
