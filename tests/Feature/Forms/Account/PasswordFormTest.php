<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Livewire\PasswordFormTestComponent;

uses(RefreshDatabase::class);

describe('PasswordForm', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('la-de-siempre'),
        ]);

        $this->actingAs($this->user);
    });

    describe('rules', function (): void {
        it('should require the current password and a new one', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->call('save')
                ->assertHasErrors(['form.current_password', 'form.password']);
        });

        it('should refuse a wrong current password', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->set('form.current_password', 'no-era-esta')
                ->set('form.password', 'una-nueva-larga')
                ->set('form.password_confirmation', 'una-nueva-larga')
                ->call('save')
                ->assertHasErrors(['form.current_password']);

            expect(Hash::check('la-de-siempre', $this->user->refresh()->password))->toBeTrue();
        });

        it('should refuse a confirmation that does not match', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->set('form.current_password', 'la-de-siempre')
                ->set('form.password', 'una-nueva-larga')
                ->set('form.password_confirmation', 'otra-cosa')
                ->call('save')
                ->assertHasErrors(['form.password']);
        });

        it('should refuse a password shorter than Fortify allows', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->set('form.current_password', 'la-de-siempre')
                ->set('form.password', 'corta')
                ->set('form.password_confirmation', 'corta')
                ->call('save')
                ->assertHasErrors(['form.password']);
        });
    });

    describe('save', function (): void {
        it('should store the new password hashed', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->set('form.current_password', 'la-de-siempre')
                ->set('form.password', 'una-nueva-larga')
                ->set('form.password_confirmation', 'una-nueva-larga')
                ->call('save')
                ->assertHasNoErrors();

            $password = $this->user->refresh()->password;

            expect(Hash::check('una-nueva-larga', $password))->toBeTrue()
                ->and($password)->not->toBe('una-nueva-larga');
        });

        it('should empty the fields once the password is changed', function (): void {
            Livewire::test(PasswordFormTestComponent::class)
                ->set('form.current_password', 'la-de-siempre')
                ->set('form.password', 'una-nueva-larga')
                ->set('form.password_confirmation', 'una-nueva-larga')
                ->call('save')
                ->assertSet('form.current_password', '')
                ->assertSet('form.password', '')
                ->assertSet('form.password_confirmation', '');
        });
    });
});
