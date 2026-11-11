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

    describe('applyDefaultGraph', function (): void {
        it('emits the Organization with its sameAs and subjectOf on the home page', function (): void {
            config()->set('seo.organization.same_as', [
                'https://www.linkedin.com/company/ejemplo',
                'https://g.page/r/ejemplo',
            ]);
            config()->set('seo.organization.subject_of', [
                ['type' => 'PodcastEpisode', 'url' => 'https://podcast.example/episodio-12', 'name' => 'Entrevista en El Podcast'],
            ]);

            $organization = homeOrganizationNode($this->get('/')->assertOk()->getContent());

            expect($organization['sameAs'])->toBe([
                'https://www.linkedin.com/company/ejemplo',
                'https://g.page/r/ejemplo',
            ]);
            expect($organization['subjectOf'])->toBe([
                ['@type' => 'PodcastEpisode', 'url' => 'https://podcast.example/episodio-12', 'name' => 'Entrevista en El Podcast'],
            ]);
        });

        it('leaves sameAs and subjectOf out of the Organization when none are configured', function (): void {
            config()->set('seo.organization.same_as', []);
            config()->set('seo.organization.subject_of', []);

            $organization = homeOrganizationNode($this->get('/')->assertOk()->getContent());

            expect($organization)->not->toHaveKey('sameAs');
            expect($organization)->not->toHaveKey('subjectOf');
        });
    });
});

/**
 * @return array<string, mixed>
 */
function homeOrganizationNode(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    $nodes = collect($matches[1])
        ->flatMap(fn (string $json): array => json_decode($json, true)['@graph'] ?? []);

    $organization = $nodes->first(fn (array $node): bool => ($node['@id'] ?? null) === rtrim((string) config('app.url'), '/').'/#organization');

    expect($organization)->not->toBeNull();

    return $organization;
}
