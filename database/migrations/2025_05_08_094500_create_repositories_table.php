<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('full_name'); // owner/repo-name
            $table->string('owner');
            $table->text('description')->nullable();
            $table->string('visibility'); // public, private
            $table->string('github_url');
            $table->string('github_id')->unique();
            $table->string('default_branch')->default('main');
            $table->string('language')->nullable();
            $table->integer('stars_count')->default(0);
            $table->integer('forks_count')->default(0);
            $table->integer('issues_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('github_created_at')->nullable();
            $table->timestamp('github_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
}; 