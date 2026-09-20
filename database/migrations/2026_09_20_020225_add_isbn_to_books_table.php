<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unique so the lookup can say "you already have this book" rather than
 * letting a duplicate be created. Nullable because the books already in the
 * library were entered by hand and have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('isbn', 13)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique('books_isbn_unique');
            $table->dropColumn('isbn');
        });
    }
};
