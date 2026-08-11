<?php

declare(strict_types=1);

use App\Services\Seo\SeoService;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;

describe('SeoService', function (): void {
    describe('setSEO', function (): void {
        it('falls back to the default share image, sized from the file on disk', function (): void {
            expect(config('seo.default_image'))->toBe('images/og-default.png');

            $path = public_path((string) config('seo.default_image'));

            expect($path)->toBeFile();
            expect(getimagesize($path))->toMatchArray([0 => 1200, 1 => 630]);

            app(SeoService::class)->setSEO('Título', 'Descripción');

            $openGraph = OpenGraph::generate();

            expect($openGraph)
                ->toContain('property="og:image" content="'.asset(config('seo.default_image')).'"')
                ->toContain('property="og:image:width" content="1200"')
                ->toContain('property="og:image:height" content="630"')
                ->toContain('property="og:image:type" content="image/png"');

            expect(TwitterCard::generate())
                ->toContain(asset(config('seo.default_image')));
        });
    });
});
