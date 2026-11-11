<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use DateTimeInterface;

class SchemaBuilder
{
    public readonly string $siteUrl;

    public readonly string $organizationId;

    public readonly string $websiteId;

    public function __construct(?string $siteUrl = null)
    {
        $this->siteUrl = rtrim((string) ($siteUrl ?? config('app.url')), '/');
        $this->organizationId = $this->siteUrl.'/#organization';
        $this->websiteId = $this->siteUrl.'/#website';
    }

    /**
     * @return array<string, mixed>
     */
    public function webPage(
        string $url,
        string $title,
        string $description,
        ?string $image = null,
        ?DateTimeInterface $datePublished = null,
        ?DateTimeInterface $dateModified = null,
    ): array {
        $node = [
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $this->websiteId],
            'about' => ['@id' => $this->organizationId],
            'inLanguage' => 'es',
        ];

        if ($image !== null) {
            $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $image];
        }

        if ($datePublished instanceof DateTimeInterface) {
            $node['datePublished'] = $datePublished->format(DateTimeInterface::ATOM);
        }

        if ($dateModified instanceof DateTimeInterface) {
            $node['dateModified'] = $dateModified->format(DateTimeInterface::ATOM);
        }

        return $node;
    }

    /**
     * Schema.org Service node, optionally narrowed to a specific Location when the
     * landing is geo-targeted (sets areaServed to a City node instead of a
     * generic Country).
     *
     * @return array<string, mixed>
     */
    public function service(Category $category, string $url, ?Location $location = null, ?string $image = null): array
    {
        $node = [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $category->name,
            'serviceType' => $category->name,
            'description' => $category->meta_description ?? $category->name,
            'url' => $url,
            'provider' => ['@id' => $this->organizationId],
            'areaServed' => $location instanceof Location
                ? ['@type' => 'City', 'name' => $location->name]
                : ['@type' => 'Country', 'name' => 'España'],
            'inLanguage' => 'es',
        ];

        if ($image !== null) {
            $node['image'] = $image;
        }

        return $node;
    }

    /**
     * Build a BreadcrumbList from the Category + Location trees of a Landing.
     * Order: Inicio → category ancestors (root→leaf) → category → city ancestors
     * (root→leaf) → city.
     *
     * @return array<string, mixed>
     */
    public function breadcrumbsForLanding(Landing $landing, string $landingUrl): array
    {
        $items = [['name' => 'Inicio', 'url' => $this->siteUrl.'/']];

        foreach ($landing->category->ancestors()->reverse() as $ancestor) {
            $items[] = ['name' => $ancestor->name, 'url' => $this->siteUrl.'/'.$ancestor->slug];
        }
        $items[] = ['name' => $landing->category->name, 'url' => $landingUrl];

        if ($landing->location) {
            foreach ($landing->location->ancestors()->reverse() as $ancestor) {
                $items[] = ['name' => $ancestor->name, 'url' => $landingUrl];
            }
            $items[] = ['name' => $landing->location->name, 'url' => $landingUrl];
        }

        return $this->breadcrumbList($items);
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $items): array
    {
        $itemListElement = [];

        foreach ($items as $i => $item) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function itemList(array $items): array
    {
        $elements = [];

        foreach ($items as $i => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $item['url'],
                'name' => $item['name'],
            ];
        }

        return [
            '@type' => 'ItemList',
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param  array<string, mixed>  $itemList  Output of itemList()
     * @return array<string, mixed>
     */
    public function collectionPage(string $url, string $title, string $description, array $itemList): array
    {
        return [
            '@type' => 'CollectionPage',
            '@id' => $url.'#collectionpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $this->websiteId],
            'inLanguage' => 'es',
            'mainEntity' => $itemList,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $faqs
     * @return array<string, mixed>
     */
    public function faqPage(array $faqs, string $questionKey = 'question', string $answerKey = 'answer'): array
    {
        $mainEntity = array_map(fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq[$questionKey],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq[$answerKey],
            ],
        ], $faqs);

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactPage(string $url, string $title, string $description): array
    {
        return [
            '@type' => 'ContactPage',
            '@id' => $url.'#contactpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $this->websiteId],
            'about' => ['@id' => $this->organizationId],
            'inLanguage' => 'es',
        ];
    }

    /**
     * @param  string|list<string>|null  $sameAs  The person's own profiles (LinkedIn, author pages...).
     * @param  list<array{type?: string, url?: string, name?: string}>  $subjectOf  Interviews, podcasts or articles about the person.
     * @return array<string, mixed>
     */
    public function person(
        string $name,
        ?string $jobTitle = null,
        ?string $image = null,
        string|array|null $sameAs = null,
        array $subjectOf = [],
    ): array {
        $node = [
            '@type' => 'Person',
            'name' => $name,
            'worksFor' => ['@id' => $this->organizationId],
        ];

        if ($jobTitle !== null) {
            $node['jobTitle'] = $jobTitle;
        }

        if ($image !== null) {
            $node['image'] = $image;
        }

        $sameAs = array_values(array_unique(array_filter((array) $sameAs)));

        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        $subjectOf = $this->subjectOf($subjectOf);

        if ($subjectOf !== []) {
            $node['subjectOf'] = $subjectOf;
        }

        return $node;
    }

    /**
     * Pages about an entity, as CreativeWork nodes of the type each entry gives.
     * An entry without a URL is dropped: it would point nowhere.
     *
     * @param  array<mixed>  $works
     * @return list<array<string, string>>
     */
    public function subjectOf(array $works): array
    {
        $nodes = [];

        foreach ($works as $work) {
            if (! is_array($work) || ! is_string($work['url'] ?? null) || $work['url'] === '') {
                continue;
            }

            $node = [
                '@type' => is_string($work['type'] ?? null) && $work['type'] !== '' ? $work['type'] : 'CreativeWork',
                'url' => $work['url'],
            ];

            if (is_string($work['name'] ?? null) && $work['name'] !== '') {
                $node['name'] = $work['name'];
            }

            $nodes[] = $node;
        }

        return $nodes;
    }
}
