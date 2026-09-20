<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->createCategories();

        $posts = [
            ['title' => 'Learning Rust', 'content' => 'Notes from starting out with Rust, and what borrowing finally clicked into place.'],
            ['title' => 'Rewriting the admin panel', 'content' => 'Why the panel left its old server-driven UI package for Inertia and Vue, and what the new contract buys.'],
            ['title' => 'Static analysis at level max', 'content' => 'What PHPStan found once the baseline was deleted and never allowed back.'],
            ['title' => 'Moving from MySQL to PostgreSQL', 'content' => 'Three bugs that only appeared once development, test and CI ran the same database.'],
            ['title' => 'A schema endpoint for the phone', 'content' => 'The panel already described its own forms and tables, so the iPhone client reads the same description.'],
            ['title' => 'Passkeys without a helper library', 'content' => 'The browser credential JSON API covers the whole ceremony on Safari 17.4 and Chrome 119.'],
        ];

        foreach ($posts as $index => $postData) {
            Post::create($postData)->categories()->attach($categories[$index % count($categories)]);
        }
    }

    /**
     * @return array<int, Category>
     */
    private function createCategories(): array
    {
        return collect(['Laravel', 'PHP', 'Rust', 'PostgreSQL', 'Swift'])
            ->map(fn (string $name): Category => Category::create(['name' => $name]))
            ->all();
    }
}
