<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared taxonomy pivot: one category can be attached to posts and books alike.
 *
 * The composite primary key is what stops the same category being attached twice.
 * Only category_id can carry a foreign key; the polymorphic side cannot, which is
 * why App\Traits\CategoriableRelation detaches these rows on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoriables', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->morphs('categoriable');

            $table->primary(['category_id', 'categoriable_id', 'categoriable_type'], 'categoriables_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoriables');
    }
};
