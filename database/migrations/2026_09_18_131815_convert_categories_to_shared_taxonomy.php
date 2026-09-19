<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categories were polymorphic children: each row belonged to exactly one post or
 * book, so the same name repeated per record and no shared list existed. They
 * become a shared taxonomy joined through a pivot instead.
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

        if (Schema::hasColumn('categories', 'categoriable_id')) {
            DB::table('categories')
                ->whereNotNull('categoriable_id')
                ->orderBy('id')
                ->chunkById(500, function (iterable $categories): void {
                    foreach ($categories as $category) {
                        DB::table('categoriables')->insertOrIgnore([
                            'category_id' => $category->id,
                            'categoriable_id' => $category->categoriable_id,
                            'categoriable_type' => $category->categoriable_type,
                        ]);
                    }
                });
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropMorphs('categoriable');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->after('slug', function (Blueprint $table): void {
                $table->nullableMorphs('categoriable');
            });
        });

        DB::table('categoriables')->orderBy('category_id')->chunk(500, function (iterable $links): void {
            foreach ($links as $link) {
                DB::table('categories')
                    ->where('id', $link->category_id)
                    ->update([
                        'categoriable_id' => $link->categoriable_id,
                        'categoriable_type' => $link->categoriable_type,
                    ]);
            }
        });

        Schema::dropIfExists('categoriables');
    }
};
