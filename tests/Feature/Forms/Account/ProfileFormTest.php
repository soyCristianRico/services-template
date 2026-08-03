<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Livewire\ProfileFormTestComponent;

uses(RefreshDatabase::class);

describe('ProfileForm', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'name' => 'Cristian',
            'email' => 'cristian@example.test',
        ]);

        $this->actingAs($this->user);
    });

    describe('setUser', function (): void {
        it('should load the account of whoever is logged in', function (): void {
            Livewire::test(ProfileFormTestComponent::class)
                ->assertSet('form.name', 'Cristian')
                ->assertSet('form.email', 'cristian@example.test');
        });
    });

    describe('rules', function (): void {
        it('should require a name and an email', function (): void {
            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.name', '')
                ->set('form.email', '')
                ->call('save')
                ->assertHasErrors(['form.name', 'form.email']);
        });

        it('should reject something that is not an email', function (): void {
            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.email', 'no-es-un-correo')
                ->call('save')
                ->assertHasErrors(['form.email']);
        });

        it('should refuse an email already taken by another account', function (): void {
            User::factory()->create(['email' => 'otra@example.test']);

            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.email', 'otra@example.test')
                ->call('save')
                ->assertHasErrors(['form.email']);
        });

        it('should allow keeping its own email untouched', function (): void {
            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.name', 'Cristian Rico')
                ->call('save')
                ->assertHasNoErrors();
        });
    });

    describe('save', function (): void {
        it('should store the name and the email', function (): void {
            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.name', 'Cristian Rico')
                ->set('form.email', 'nuevo@example.test')
                ->call('save')
                ->assertHasNoErrors();

            expect($this->user->refresh())
                ->name->toBe('Cristian Rico')
                ->email->toBe('nuevo@example.test');
        });

        it('should never touch an account other than the one logged in', function (): void {
            $other = User::factory()->create(['name' => 'Ajena']);

            Livewire::test(ProfileFormTestComponent::class)
                ->set('form.name', 'Cristian Rico')
                ->call('save');

            expect($other->refresh()->name)->toBe('Ajena');
        });
    });
});
