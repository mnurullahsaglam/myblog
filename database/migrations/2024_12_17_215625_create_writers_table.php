<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('writers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('bio')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('death_place')->nullable();
            $table->year('birth_year')->nullable();
            $table->year('death_year')->nullable();
            $table->string('image')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writers');
    }
};
