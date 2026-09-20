<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both tables carried id columns with neither an index nor a constraint.
     *
     * The board filters tasks by status and orders them by sort_order on every
     * render, and MoveTask reindexes a whole column per drag, so those two are
     * indexed together. Deleting a project or a client previously left rows
     * pointing at nothing; the columns are nullable, so the references null out
     * rather than cascading a delete the user did not ask for.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['status', 'sort_order'], 'tasks_status_sort_order_index');

            $table->foreign('project_id', 'tasks_project_id_foreign')
                ->references('id')->on('projects')->nullOnDelete();

            $table->foreign('repository_id', 'tasks_repository_id_foreign')
                ->references('id')->on('repositories')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreign('client_id', 'projects_client_id_foreign')
                ->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign('tasks_project_id_foreign');
            $table->dropForeign('tasks_repository_id_foreign');

            // MySQL keeps the index a dropped foreign key created, so the
            // rollback is only symmetric if those go too.
            $table->dropIndex('tasks_project_id_foreign');
            $table->dropIndex('tasks_repository_id_foreign');
            $table->dropIndex('tasks_status_sort_order_index');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign('projects_client_id_foreign');
            $table->dropIndex('projects_client_id_foreign');
        });
    }
};
