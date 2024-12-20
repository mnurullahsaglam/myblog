<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->create([
                'name' => config('app.admin_name'),
                'email' => config('app.admin_email'),
            ]);
    }
}
