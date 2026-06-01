<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waka_time_summary_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waka_time_summary_id')
                ->constrained()
                ->cascadeOnDelete();
            // project | language | editor | os | category
            $table->string('type')->index();
            $table->string('name');
            $table->unsignedBigInteger('seconds')->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waka_time_summary_entries');
    }
};
