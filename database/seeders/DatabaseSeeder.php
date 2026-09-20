<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountSeeder::class,
            BlogSeeder::class,
            LibrarySeeder::class,
            WorkSeeder::class,
            BudgetSeeder::class,
            UtilitySeeder::class,
        ]);
    }
}
