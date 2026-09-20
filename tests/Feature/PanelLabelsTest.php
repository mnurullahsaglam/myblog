<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * @return array<int, array{file: string, tag: string}>
 */
function openingTags(): array
{
    $tags = [];

    foreach (Finder::create()->files()->in(resource_path('js'))->name('*.vue') as $file) {
        $source = $file->getContents();
        $length = mb_strlen($source);
        $offset = 0;

        while (($start = mb_strpos($source, '<', $offset)) !== false) {
            $offset = $start + 1;

            if (preg_match('/[A-Za-z]/', mb_substr($source, $start + 1, 1)) !== 1) {
                continue;
            }

            $quote = null;

            for ($i = $start + 1; $i < $length; $i++) {
                $character = mb_substr($source, $i, 1);

                if ($quote !== null) {
                    if ($character === $quote) {
                        $quote = null;
                    }

                    continue;
                }

                if ($character === '"' || $character === "'") {
                    $quote = $character;

                    continue;
                }

                if ($character === '>') {
                    $tags[] = [
                        'file' => $file->getRelativePathname(),
                        'tag' => mb_substr($source, $start, $i - $start + 1),
                    ];
                    $offset = $i;

                    break;
                }
            }
        }
    }

    return $tags;
}

it('marks every uppercase label as English', function (): void {
    $offenders = [];

    foreach (openingTags() as $tag) {
        $wearsRecipe = str_contains($tag['tag'], 'uppercase')
            || str_contains($tag['tag'], ':class="labelClass"');

        if ($wearsRecipe && ! str_contains($tag['tag'], 'lang="en"')) {
            $offenders[] = $tag['file'].': '.mb_substr(preg_replace('/\s+/', ' ', $tag['tag']) ?? '', 0, 120);
        }
    }

    expect($offenders)->toBeEmpty('Uppercase labels missing lang="en": '.implode('; ', $offenders));
});

it('keeps the shared label recipe uppercase', function (): void {
    $files = [
        'Components/Form/FormField.vue',
        'Components/Board/TaskDialog.vue',
        'pages/Settings.vue',
        'pages/Budget/Debts/PayDebtDialog.vue',
    ];

    foreach ($files as $file) {
        expect(file_get_contents(resource_path('js/'.$file)))
            ->toContain("const labelClass = 'font-mono text-[11px] font-semibold uppercase");
    }
});

it('has no translation layer in the panel', function (): void {
    foreach (Finder::create()->files()->in(resource_path('js'))->name('*.vue') as $file) {
        expect($file->getContents())->not->toContain('$t(');
    }
});
