<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Models\Category;
use App\Models\Landing;
use Livewire\Livewire;
use Tests\Support\Livewire\LandingFormTestComponent;

describe('LandingForm', function (): void {
    describe('initialization', function (): void {
        it('should default to draft with no publish date', function (): void {
            Livewire::test(LandingFormTestComponent::class)
                ->assertSet('form.status', LandingStatus::Draft)
                ->assertSet('form.publish_at', null);
        });
    });

    describe('rules', function (): void {
        it('should require a category and a slug', function (): void {
            Livewire::test(LandingFormTestComponent::class)
                ->call('save')
                ->assertHasErrors(['form.category_id', 'form.slug']);
        });

        it('should require publish_at when status is scheduled', function (): void {
            $category = Category::factory()->create();

            Livewire::test(LandingFormTestComponent::class)
                ->set('form.category_id', $category->id)
                ->set('form.slug', 'test-slug')
                ->set('form.status', LandingStatus::Scheduled)
                ->call('save')
                ->assertHasErrors(['form.publish_at']);
        });
    });

    describe('save', function (): void {
        it('should persist status and clear publish_at for non-scheduled landings', function (): void {
            $category = Category::factory()->create();

            Livewire::test(LandingFormTestComponent::class)
                ->set('form.category_id', $category->id)
                ->set('form.slug', 'published-slug')
                ->set('form.status', LandingStatus::Published)
                ->set('form.publish_at', now()->addDay()->format('Y-m-d'))
                ->call('save')
                ->assertHasNoErrors();

            $landing = Landing::where('slug', 'published-slug')->first();
            expect($landing->status)->toBe(LandingStatus::Published)
                ->and($landing->publish_at)->toBeNull();
        });

        it('should persist a scheduled landing with its publish date', function (): void {
            $category = Category::factory()->create();

            Livewire::test(LandingFormTestComponent::class)
                ->set('form.category_id', $category->id)
                ->set('form.slug', 'scheduled-slug')
                ->set('form.status', LandingStatus::Scheduled)
                ->set('form.publish_at', now()->addDays(4)->format('Y-m-d'))
                ->call('save')
                ->assertHasNoErrors();

            $landing = Landing::where('slug', 'scheduled-slug')->first();
            expect($landing->status)->toBe(LandingStatus::Scheduled)
                ->and($landing->publish_at)->not->toBeNull();
        });
    });

    describe('status-date linkage', function (): void {
        it('should schedule when a date is set and revert to draft when cleared', function (): void {
            $component = Livewire::test(LandingFormTestComponent::class)
                ->set('form.publish_at', now()->addDay()->format('Y-m-d'))
                ->call('syncStatusFromDate')
                ->assertSet('form.status', LandingStatus::Scheduled);

            $component
                ->set('form.publish_at', '')
                ->call('syncStatusFromDate')
                ->assertSet('form.status', LandingStatus::Draft);
        });

        it('should drop the publish date when status is not scheduled', function (): void {
            Livewire::test(LandingFormTestComponent::class)
                ->set('form.status', LandingStatus::Published)
                ->set('form.publish_at', now()->addDay()->format('Y-m-d'))
                ->call('syncDateFromStatus')
                ->assertSet('form.publish_at', null);
        });
    });
});
