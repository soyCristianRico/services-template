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

describe('Admin\\Landings\\Edit', function (): void {
    describe('create', function (): void {
        it('should auto-fill slug as {service}-en-{city} when both are picked', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.location_id', $location->id)
                ->assertSet('form.slug', 'alquiler-generadores-madrid');
        });

        it('should auto-fill slug as service slug when no city is picked', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->assertSet('form.slug', 'alquiler-generadores');
        });

        it('should NOT overwrite an existing custom slug when service or city changes', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);

            Livewire::test('pages::admin.landings.edit')
                ->set('form.slug', 'grupos-electrogenos-madrid')
                ->set('form.category_id', $service->id)
                ->set('form.location_id', $location->id)
                ->assertSet('form.slug', 'grupos-electrogenos-madrid');
        });

        it('should persist a new landing as draft by default', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.location_id', $location->id)
                ->set('form.slug', 'test-slug')
                ->call('save')
                ->assertRedirect();

            $landing = Landing::where('slug', 'test-slug')->first();
            expect($landing)->not->toBeNull();
            expect($landing->status)->toBe(LandingStatus::Draft);
        });

        it('should schedule the landing when a publish date is set', function (): void {
            $service = Category::factory()->create();

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.slug', 'scheduled-slug')
                ->set('form.publish_at', now()->addDays(3)->format('Y-m-d'))
                ->assertSet('form.status', LandingStatus::Scheduled)
                ->call('save')
                ->assertHasNoErrors();

            $landing = Landing::where('slug', 'scheduled-slug')->first();
            expect($landing->status)->toBe(LandingStatus::Scheduled)
                ->and($landing->publish_at)->not->toBeNull();
        });

        it('should revert to draft when the publish date is cleared', function (): void {
            $service = Category::factory()->create();

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.publish_at', now()->addDays(3)->format('Y-m-d'))
                ->assertSet('form.status', LandingStatus::Scheduled)
                ->set('form.publish_at', '')
                ->assertSet('form.status', LandingStatus::Draft);
        });

        it('should reject invalid JSON content', function (): void {
            $service = Category::factory()->create();

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.slug', 'test-slug')
                ->set('form.contentJson', '{this is not valid json')
                ->call('save')
                ->assertHasErrors(['form.contentJson']);

            expect(Landing::count())->toBe(0);
        });

        it('should accept and store valid JSON content as array', function (): void {
            $service = Category::factory()->create();

            Livewire::test('pages::admin.landings.edit')
                ->set('form.category_id', $service->id)
                ->set('form.slug', 'test-slug')
                ->set('form.contentJson', '{"hero": {"title": "OK"}}')
                ->call('save')
                ->assertHasNoErrors();

            $landing = Landing::where('slug', 'test-slug')->first();
            expect($landing->content)->toBe(['hero' => ['title' => 'OK']]);
        });
    });

    describe('edit', function (): void {
        it('should preload form including content as pretty-printed JSON', function (): void {
            $service = Category::factory()->create();
            $landing = Landing::factory()->forCategory($service)->create([
                'title' => 'Custom title',
                'content' => ['hero' => ['title' => 'Pretty']],
                'meta_description' => 'd',
            ]);

            $component = Livewire::test('pages::admin.landings.edit', ['landing' => $landing]);

            $component
                ->assertSet('form.id', $landing->id)
                ->assertSet('form.title', 'Custom title')
                ->assertSet('form.meta_description', 'd');

            expect($component->get('form.contentJson'))->toContain('"hero"');
        });

        it('should update an existing landing', function (): void {
            $landing = Landing::factory()->create();

            Livewire::test('pages::admin.landings.edit', ['landing' => $landing])
                ->set('form.title', 'Nuevo título')
                ->call('save')
                ->assertHasNoErrors();

            expect($landing->refresh()->title)->toBe('Nuevo título');
        });
    });
});
