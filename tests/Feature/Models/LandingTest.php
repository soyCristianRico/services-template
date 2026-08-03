<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use Carbon\CarbonInterface;

describe('Landing', function (): void {
    describe('slug auto-generation', function (): void {
        it('should build slug from category alone when city is null', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);

            $landing = Landing::factory()->forCategory($service)->create();

            expect($landing->slug)->toBe('alquiler-generadores');
        });

        it('should build slug as {category}-{city} when both are set', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);

            $landing = Landing::factory()->forCategory($service)->inLocation($location)->create();

            expect($landing->slug)->toBe('alquiler-generadores-madrid');
        });

        it('should not override an explicit slug', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);

            $landing = Landing::factory()
                ->forCategory($service)
                ->inLocation($location)
                ->create(['slug' => 'grupos-electrogenos-madrid']);

            expect($landing->slug)->toBe('grupos-electrogenos-madrid');
        });
    });

    describe('relations', function (): void {
        it('should belong to a service and optionally a city', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            $landing = Landing::factory()->forCategory($service)->inLocation($location)->create();

            expect($landing->category->id)->toBe($service->id);
            expect($landing->location->id)->toBe($location->id);
        });

        it('should accept null city for service-only landings', function (): void {
            $landing = Landing::factory()->create();

            expect($landing->location)->toBeNull();
            expect($landing->location_id)->toBeNull();
        });
    });

    describe('scopes', function (): void {
        it('should scope to published only', function (): void {
            Landing::factory()->count(3)->create();
            Landing::factory()->draft()->count(2)->create();
            Landing::factory()->scheduled()->count(1)->create();

            expect(Landing::published()->count())->toBe(3);
        });

        it('should scope due-for-publishing to scheduled landings past their date', function (): void {
            $due = Landing::factory()->scheduled(now()->subDay())->create();
            Landing::factory()->scheduled(now()->addDay())->create(); // future
            Landing::factory()->draft()->create();
            Landing::factory()->published()->create();

            $result = Landing::dueForPublishing()->get();

            expect($result)->toHaveCount(1)
                ->and($result->first()->id)->toBe($due->id);
        });

        it('should match a combination by service and city', function (): void {
            $serviceA = Category::factory()->create();
            $serviceB = Category::factory()->create();
            $location = Location::factory()->create();

            $target = Landing::factory()->forCategory($serviceA)->inLocation($location)->create();
            Landing::factory()->forCategory($serviceB)->inLocation($location)->create();
            Landing::factory()->forCategory($serviceA)->create(); // service-only

            expect(Landing::forCombination($serviceA->id, $location->id)->first()->id)->toBe($target->id);
        });

        it('should match service-only landings when city is null', function (): void {
            $service = Category::factory()->create();
            $target = Landing::factory()->forCategory($service)->create();
            Landing::factory()->forCategory($service)->inLocation(Location::factory()->create())->create();

            expect(Landing::forCombination($service->id, null)->first()->id)->toBe($target->id);
        });
    });

    describe('casts', function (): void {
        it('should cast content to array', function (): void {
            $landing = Landing::factory()->create([
                'content' => ['hero' => ['title' => 'Test', 'cta' => 'Pedir presupuesto']],
            ]);

            expect($landing->content)->toBeArray()
                ->and($landing->content['hero']['title'])->toBe('Test');
        });

        it('should cast status to the LandingStatus enum', function (): void {
            $landing = Landing::factory()->draft()->create();

            expect($landing->status)->toBe(LandingStatus::Draft);
        });

        it('should cast publish_at to a datetime', function (): void {
            $landing = Landing::factory()->scheduled(now()->addDays(2))->create();

            expect($landing->publish_at)->toBeInstanceOf(CarbonInterface::class);
        });
    });
});
