<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('waka_time_summaries', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('total_seconds')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waka_time_summaries');
    }
};
