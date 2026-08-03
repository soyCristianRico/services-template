<?php

declare(strict_types=1);

/**
 * `flux:callout` lays its slot out as a flex column, and flex blockifies every
 * element child. So an inline tag dropped straight into the slot —a link, a
 * `<strong>`— stops being inline: it takes a line of its own, and the text that
 * followed it, punctuation included, falls onto yet another line.
 *
 * The fix is always the same: wrap the prose in `flux:callout.text`, which is a
 * single flex item, and let the inline tags live inside it.
 */
function calloutTemplates(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views')),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getPathname(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * The slot of every `flux:callout` in a template, with the content of its own
 * sub-components (`heading`, `text`, `link`) removed — what is left is what the
 * flex container will turn into items.
 *
 * @return array<int, array{line: int, slot: string}>
 */
function calloutSlots(string $template): array
{
    $source = (string) file_get_contents($template);

    preg_match_all('/<flux:callout\b[^>]*?(?<!\/)>(.*?)<\/flux:callout>/s', $source, $matches, PREG_OFFSET_CAPTURE);

    $slots = [];

    foreach ($matches[1] as $index => [$slot, $offset]) {
        $bare = preg_replace(
            ['/<flux:callout\.\w+\b[^>]*?(?<!\/)>.*?<\/flux:callout\.\w+>/s', '/<flux:callout\.\w+\b[^>]*?\/>/s'],
            '',
            $slot,
        );

        $slots[] = [
            'line' => substr_count(substr($source, 0, (int) $matches[0][$index][1]), "\n") + 1,
            'slot' => (string) $bare,
        ];
    }

    return $slots;
}

describe('CalloutSlot', function (): void {
    describe('inline_children', function (): void {
        it('should keep inline tags out of the flex column', function (): void {
            $offenders = [];

            foreach (calloutTemplates() as $template) {
                foreach (calloutSlots($template) as $callout) {
                    if (preg_match('/<(a|strong|em|b|i|code|span|small|br)\b/', $callout['slot'], $tag)) {
                        $offenders[] = sprintf(
                            '%s:%d has a bare <%s> in the callout slot',
                            str_replace(resource_path('views').'/', '', $template),
                            $callout['line'],
                            $tag[1],
                        );
                    }
                }
            }

            expect($offenders)->toBe([]);
        });
    });
});
