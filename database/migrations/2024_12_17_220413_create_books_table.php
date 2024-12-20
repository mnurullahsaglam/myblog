<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('writer_id')->constrained('writers');
            $table->foreignId('publisher_id')->constrained('publishers');

            $table->string('name');
            $table->string('original_name')->nullable();
            $table->string('slug');
            $table->integer('page_count')->nullable();
            $table->year('publication_date')->nullable();
            $table->string('publication_location')->nullable();
            $table->integer('edition_number')->nullable();
            $table->string('image')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
