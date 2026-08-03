<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Landings\\Matrix', function (): void {
    describe('rendering', function (): void {
        it('should render an empty hint when there are no services', function (): void {
            Livewire::test('pages::admin.landings.matrix')
                ->assertOk()
                ->assertSee('Selecciona al menos una categoría');
        });

        it('should preselect the first 10 services and locations on mount', function (): void {
            Category::factory()->count(12)->create();
            Location::factory()->count(12)->create();

            $component = Livewire::test('pages::admin.landings.matrix');

            expect($component->get('categoryIds'))->toHaveCount(10);
            expect($component->get('locationIds'))->toHaveCount(10);
        });

        it('should mark cells whose Landing already exists and is published', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            Landing::factory()->forCategory($service)->inLocation($location)->create();

            $component = Livewire::test('pages::admin.landings.matrix');
            $key = $service->id.'-'.$location->id;

            expect($component->get('checked')[$key] ?? false)->toBeTrue();
        });

        it('should NOT mark cells whose Landing is a draft', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            Landing::factory()->forCategory($service)->inLocation($location)->draft()->create();

            $component = Livewire::test('pages::admin.landings.matrix');
            $key = $service->id.'-'.$location->id;

            expect($component->get('checked')[$key] ?? false)->toBeFalse();
        });
    });

    describe('applyChanges', function (): void {
        it('should create new Landings for marked cells without an existing row', function (): void {
            $service = Category::factory()->create();
            $location1 = Location::factory()->create(['slug' => 'madrid']);
            $location2 = Location::factory()->create(['slug' => 'barcelona']);

            Livewire::test('pages::admin.landings.matrix')
                ->set('checked', [
                    $service->id.'-'.$location1->id => true,
                    $service->id.'-'.$location2->id => true,
                ])
                ->call('applyChanges');

            expect(Landing::count())->toBe(2);
            expect(Landing::where('location_id', $location1->id)->where('status', LandingStatus::Published)->exists())->toBeTrue();
            expect(Landing::where('location_id', $location2->id)->where('status', LandingStatus::Published)->exists())->toBeTrue();
        });

        it('should republish Landings that were drafts and are now marked', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            $landing = Landing::factory()->forCategory($service)->inLocation($location)->draft()->create();

            Livewire::test('pages::admin.landings.matrix')
                ->set('checked', [$service->id.'-'.$location->id => true])
                ->call('applyChanges');

            expect($landing->refresh()->status)->toBe(LandingStatus::Published);
        });

        it('should set Landings back to draft when they are now unmarked', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            $landing = Landing::factory()->forCategory($service)->inLocation($location)->create();

            Livewire::test('pages::admin.landings.matrix')
                ->set('checked', [$service->id.'-'.$location->id => false])
                ->call('applyChanges');

            expect($landing->refresh()->status)->toBe(LandingStatus::Draft);
        });

        it('should NOT delete Landings when unmarked — only deactivate', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            $landing = Landing::factory()->forCategory($service)->inLocation($location)->create([
                'title' => 'Brief custom escrito a mano',
                'content' => ['hero' => ['title' => 'No quiero perder esto']],
            ]);

            Livewire::test('pages::admin.landings.matrix')
                ->set('checked', [$service->id.'-'.$location->id => false])
                ->call('applyChanges');

            $stored = Landing::find($landing->id);
            expect($stored)->not->toBeNull();
            expect($stored->title)->toBe('Brief custom escrito a mano');
            expect($stored->content)->toBe(['hero' => ['title' => 'No quiero perder esto']]);
            expect($stored->status)->toBe(LandingStatus::Draft);
        });

        it('should support service-only landings via the includeCategoryOnly flag', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);

            Livewire::test('pages::admin.landings.matrix')
                ->set('locationIds', [])
                ->set('includeCategoryOnly', true)
                ->set('checked', [$service->id.'-none' => true])
                ->call('applyChanges');

            $landing = Landing::where('category_id', $service->id)->whereNull('location_id')->first();
            expect($landing)->not->toBeNull();
            expect($landing->slug)->toBe('alquiler-generadores');
        });

        it('should emit a status message summarising the changes', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();

            $component = Livewire::test('pages::admin.landings.matrix')
                ->set('checked', [$service->id.'-'.$location->id => true])
                ->call('applyChanges');

            expect($component->get('statusMessage'))->toContain('creadas');
        });
    });
});
