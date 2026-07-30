<?php

declare(strict_types=1);

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MenuItem', function (): void {
    describe('children', function (): void {
        it('should nest children under their parent in position order', function (): void {
            $parent = MenuItem::factory()->create();
            MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'Segundo', 'position' => 2]);
            MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'Primero', 'position' => 1]);

            expect($parent->children)->toHaveCount(2)
                ->and($parent->children->first()->label)->toBe('Primero');
        });

        it('should expose the parent from the child', function (): void {
            $parent = MenuItem::factory()->create();
            $child = MenuItem::factory()->create(['parent_id' => $parent->id]);

            expect($child->parent->id)->toBe($parent->id);
        });
    });

    describe('scopeIn', function (): void {
        it('should return only items of a location, ordered by position', function (): void {
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'position' => 2]);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'position' => 1]);
            MenuItem::factory()->create(['location' => MenuLocation::Footer]);

            $header = MenuItem::in(MenuLocation::Header)->get();

            expect($header)->toHaveCount(2)
                ->and($header->first()->position)->toBe(1);
        });
    });

    describe('scopeRoots', function (): void {
        it('should exclude nested items', function (): void {
            $parent = MenuItem::factory()->create();
            MenuItem::factory()->create(['parent_id' => $parent->id]);

            expect(MenuItem::roots()->count())->toBe(1);
        });
    });

    describe('casts', function (): void {
        it('should cast location and type to enums', function (): void {
            $item = MenuItem::factory()->create([
                'location' => MenuLocation::Footer,
                'type' => MenuItemType::DynamicBlock,
                'source' => 'services',
            ]);

            expect($item->refresh()->location)->toBe(MenuLocation::Footer)
                ->and($item->type)->toBe(MenuItemType::DynamicBlock);
        });
    });
});
