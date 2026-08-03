<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('Admin\\Profile', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'name' => 'Cristian',
            'password' => Hash::make('la-de-siempre'),
        ]);
    });

    describe('route', function (): void {
        it('should answer at the address the sidebar links to', function (): void {
            $this->actingAs($this->user)
                ->get('/admin/profile')
                ->assertSuccessful()
                ->assertSee('Tu perfil');
        });

        it('should send a guest to the login screen', function (): void {
            $this->get('/admin/profile')->assertRedirect('/login');
        });
    });

    describe('save', function (): void {
        it('should preload the account and store the changes', function (): void {
            Livewire::actingAs($this->user)
                ->test('pages::admin.profile')
                ->assertSet('form.name', 'Cristian')
                ->set('form.name', 'Cristian Rico')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('status', 'Datos de la cuenta guardados.');

            expect($this->user->refresh()->name)->toBe('Cristian Rico');
        });
    });

    describe('savePassword', function (): void {
        it('should change the password', function (): void {
            Livewire::actingAs($this->user)
                ->test('pages::admin.profile')
                ->set('passwordForm.current_password', 'la-de-siempre')
                ->set('passwordForm.password', 'una-nueva-larga')
                ->set('passwordForm.password_confirmation', 'una-nueva-larga')
                ->call('savePassword')
                ->assertHasNoErrors()
                ->assertSet('status', 'Contraseña actualizada.');

            expect(Hash::check('una-nueva-larga', $this->user->refresh()->password))->toBeTrue();
        });

        it('should report a wrong current password without changing anything', function (): void {
            Livewire::actingAs($this->user)
                ->test('pages::admin.profile')
                ->set('passwordForm.current_password', 'no-era-esta')
                ->set('passwordForm.password', 'una-nueva-larga')
                ->set('passwordForm.password_confirmation', 'una-nueva-larga')
                ->call('savePassword')
                ->assertHasErrors(['passwordForm.current_password'])
                ->assertSet('status', '');

            expect(Hash::check('la-de-siempre', $this->user->refresh()->password))->toBeTrue();
        });
    });
});
