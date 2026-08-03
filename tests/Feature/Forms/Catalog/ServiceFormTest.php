<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use Livewire\Livewire;
use Tests\Support\Livewire\ServiceFormTestComponent;

describe('ServiceForm', function (): void {
    describe('rules', function (): void {
        it('should require a category, name and slug', function (): void {
            Livewire::test(ServiceFormTestComponent::class)
                ->call('save')
                ->assertHasErrors(['form.category_id', 'form.name', 'form.slug']);
        });
    });

    describe('save', function (): void {
        it('should persist a service', function (): void {
            $category = Category::factory()->create();

            Livewire::test(ServiceFormTestComponent::class)
                ->set('form.category_id', $category->id)
                ->set('form.name', 'Amoladora')
                ->set('form.slug', 'amoladora')
                ->call('save')
                ->assertHasNoErrors();

            expect(Service::where('slug', 'amoladora')->exists())->toBeTrue();
        });

        it('should sync additional categories excluding the primary one', function (): void {
            $primary = Category::factory()->create();
            $extra = Category::factory()->create();

            Livewire::test(ServiceFormTestComponent::class)
                ->set('form.category_id', $primary->id)
                ->set('form.additional_category_ids', [$extra->id, $primary->id])
                ->set('form.name', 'Kit')
                ->set('form.slug', 'kit')
                ->call('save')
                ->assertHasNoErrors();

            $service = Service::where('slug', 'kit')->first();
            expect($service->additionalCategories()->pluck('categories.id')->all())->toBe([$extra->id]);
        });

        it('should load additional categories from a persisted service', function (): void {
            $service = Service::factory()->create();
            $extra = Category::factory()->create();
            $service->additionalCategories()->attach($extra);

            Livewire::test(ServiceFormTestComponent::class, ['service' => $service])
                ->assertSet('form.additional_category_ids', [$extra->id]);
        });
    });
});
