<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status');
            $table->integer('sort_order')->nullable();

            $table->string('github_issue_number')->nullable();
            $table->string('github_issue_url')->nullable();
            $table->string('github_issue_state')->nullable(); // open, closed
            $table->json('github_issue_labels')->nullable();
            $table->string('github_assignee')->nullable();
            $table->timestamp('github_created_at')->nullable();
            $table->timestamp('github_updated_at')->nullable();
            $table->timestamp('github_closed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }
};
