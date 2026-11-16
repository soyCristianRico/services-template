<?php

declare(strict_types=1);

/**
 * Every text Flux shows through __() (a select's «No results found», a date
 * picker's «Previous month»…), relative to the project root. Pagination
 * keys come from lang/es/pagination.php.
 *
 * @return list<string>
 */
function fluxTranslationKeys(): array
{
    $root = dirname(__DIR__, 2);
    $keys = [];

    foreach (['vendor/livewire/flux/stubs', 'vendor/livewire/flux-pro/stubs'] as $folder) {
        if (! is_dir($root.'/'.$folder)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'\\)/", (string) file_get_contents($file->getPathname()), $matches);
            array_push($keys, ...$matches[1]);
        }
    }

    $keys = array_filter(array_unique($keys), fn (string $key): bool => ! str_starts_with($key, 'pagination.'));
    sort($keys);

    return array_values($keys);
}

describe('Flux translations', function (): void {
    describe('lang/es.json', function (): void {
        it('should translate every text Flux shows', function (): void {
            $spanish = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/es.json'), true, flags: JSON_THROW_ON_ERROR);

            $missing = array_values(array_diff(fluxTranslationKeys(), array_keys($spanish)));

            expect(fluxTranslationKeys())->not->toBeEmpty()
                ->and($missing)->toBe([], 'Flux shows these in English: add them to lang/es.json');
        });
    });
});
