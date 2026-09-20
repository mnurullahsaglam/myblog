<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('endpoint');
            $table->string('payload_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->text('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }
};
