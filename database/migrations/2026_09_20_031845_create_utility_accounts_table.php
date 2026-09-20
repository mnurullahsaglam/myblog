<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_accounts', function (Blueprint $table): void {
            $table->id();

            $table->string('type')->index();
            $table->string('provider');
            $table->string('subscriber_no')->nullable();
            $table->string('label');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }
};
