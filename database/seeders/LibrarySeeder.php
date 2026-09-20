<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Writer;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $publishers = collect([
            'İletişim Yayınları',
            'Yapı Kredi Yayınları',
            'Can Yayınları',
            "O'Reilly Media",
            'Pragmatic Bookshelf',
        ])->mapWithKeys(fn (string $name): array => [$name => Publisher::create(['name' => $name])])->all();

        $writers = [
            [
                'name' => 'Oğuz Atay',
                'bio' => 'Novelist and engineer, best known for Tutunamayanlar.',
                'birth_place' => 'İnebolu',
                'death_place' => 'İstanbul',
                'birth_year' => 1934,
                'death_year' => 1977,
            ],
            [
                'name' => 'Sabahattin Ali',
                'bio' => 'Novelist, poet and journalist.',
                'birth_place' => 'Eğridere',
                'death_place' => 'Kırklareli',
                'birth_year' => 1907,
                'death_year' => 1948,
            ],
            [
                'name' => 'Ursula K. Le Guin',
                'bio' => 'Author of science fiction and fantasy.',
                'birth_place' => 'Berkeley',
                'death_place' => 'Portland',
                'birth_year' => 1929,
                'death_year' => 2018,
            ],
            [
                'name' => 'Steve Klabnik',
                'bio' => 'Co-author of The Rust Programming Language.',
                'birth_place' => 'Pittsburgh',
                'birth_year' => 1986,
            ],
            [
                'name' => 'Martin Fowler',
                'bio' => 'Writer on software design and refactoring.',
                'birth_place' => 'Walsall',
                'birth_year' => 1963,
            ],
        ];

        $created = collect($writers)->mapWithKeys(
            fn (array $writer): array => [$writer['name'] => Writer::create($writer)]
        )->all();

        $books = [
            [
                'writer' => 'Oğuz Atay',
                'publisher' => 'İletişim Yayınları',
                'name' => 'Tutunamayanlar',
                'isbn' => '9789754702118',
                'page_count' => 724,
                'publication_date' => 1972,
                'publication_location' => 'İstanbul',
                'edition_number' => 92,
            ],
            [
                'writer' => 'Oğuz Atay',
                'publisher' => 'İletişim Yayınları',
                'name' => 'Tehlikeli Oyunlar',
                'isbn' => '9789754702125',
                'page_count' => 456,
                'publication_date' => 1973,
                'publication_location' => 'İstanbul',
                'edition_number' => 61,
            ],
            [
                'writer' => 'Sabahattin Ali',
                'publisher' => 'Yapı Kredi Yayınları',
                'name' => 'Kürk Mantolu Madonna',
                'isbn' => '9789753638029',
                'page_count' => 160,
                'publication_date' => 1943,
                'publication_location' => 'İstanbul',
                'edition_number' => 138,
            ],
            [
                'writer' => 'Ursula K. Le Guin',
                'publisher' => 'Can Yayınları',
                'name' => 'Karanlığın Sol Eli',
                'original_name' => 'The Left Hand of Darkness',
                'isbn' => '9789750726446',
                'page_count' => 320,
                'publication_date' => 1969,
                'publication_location' => 'İstanbul',
                'edition_number' => 12,
            ],
            [
                'writer' => 'Steve Klabnik',
                'publisher' => "O'Reilly Media",
                'name' => 'The Rust Programming Language',
                'isbn' => '9781718503106',
                'page_count' => 560,
                'publication_date' => 2023,
                'publication_location' => 'San Francisco',
                'edition_number' => 2,
            ],
            [
                'writer' => 'Martin Fowler',
                'publisher' => 'Pragmatic Bookshelf',
                'name' => 'Refactoring',
                'isbn' => '9780134757599',
                'page_count' => 448,
                'publication_date' => 2018,
                'publication_location' => 'Boston',
                'edition_number' => 2,
            ],
        ];

        $categories = collect(['Roman', 'Bilim Kurgu', 'Yazılım'])
            ->map(fn (string $name): Category => Category::firstOrCreate(['name' => $name]))
            ->all();

        foreach ($books as $index => $bookData) {
            $writer = $created[$bookData['writer']];
            $publisher = $publishers[$bookData['publisher']];

            unset($bookData['writer'], $bookData['publisher']);

            Book::create($bookData + [
                'writer_id' => $writer->id,
                'publisher_id' => $publisher->id,
            ])->categories()->attach($categories[$index % count($categories)]);
        }
    }
}
