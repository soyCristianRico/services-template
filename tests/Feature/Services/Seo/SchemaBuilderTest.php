<?php

declare(strict_types=1);

use App\Services\Seo\SchemaBuilder;

describe('SchemaBuilder', function (): void {
    describe('person', function (): void {
        it('should list several sameAs profiles and the works about the person', function (): void {
            $node = (new SchemaBuilder('https://example.com'))->person(
                name: 'Ana Pérez',
                sameAs: ['https://www.linkedin.com/in/ana', 'https://autores.example/ana'],
                subjectOf: [['type' => 'VideoObject', 'url' => 'https://video.example/ponencia', 'name' => 'Ponencia']],
            );

            expect($node['sameAs'])->toBe(['https://www.linkedin.com/in/ana', 'https://autores.example/ana']);
            expect($node['subjectOf'])->toBe([['@type' => 'VideoObject', 'url' => 'https://video.example/ponencia', 'name' => 'Ponencia']]);
        });

        it('should still take a single sameAs URL', function (): void {
            $node = (new SchemaBuilder('https://example.com'))->person('Ana Pérez', sameAs: 'https://www.linkedin.com/in/ana');

            expect($node['sameAs'])->toBe(['https://www.linkedin.com/in/ana']);
        });

        it('should leave sameAs and subjectOf out when there are none', function (): void {
            $node = (new SchemaBuilder('https://example.com'))->person('Ana Pérez');

            expect($node)->not->toHaveKeys(['sameAs', 'subjectOf']);
        });
    });

    describe('subjectOf', function (): void {
        it('should default to CreativeWork and drop entries without a URL', function (): void {
            $nodes = (new SchemaBuilder('https://example.com'))->subjectOf([
                ['url' => 'https://prensa.example/entrevista'],
                ['type' => 'Article', 'name' => 'Sin enlace'],
                'not-an-entry',
            ]);

            expect($nodes)->toBe([['@type' => 'CreativeWork', 'url' => 'https://prensa.example/entrevista']]);
        });
    });
});
