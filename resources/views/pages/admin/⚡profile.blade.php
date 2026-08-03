<?php

declare(strict_types=1);

use App\Livewire\Forms\Account\PasswordForm;
use App\Livewire\Forms\Account\ProfileForm;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public ProfileForm $form;

    public PasswordForm $passwordForm;

    public string $status = '';

    public function mount(): void
    {
        $this->form->setUser(auth()->user());
    }

    public function save(): void
    {
        $this->form->save();

        $this->status = 'Datos de la cuenta guardados.';
    }

    public function savePassword(): void
    {
        $this->passwordForm->save();

        $this->status = 'Contraseña actualizada.';
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Tu perfil</flux:heading>
        <flux:button :href="route('admin.dashboard')" variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

    @if ($status)
        <flux:callout icon="check-circle" color="green">{{ $status }}</flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">Datos de la cuenta</flux:heading>

        <flux:field>
            <flux:label>Nombre</flux:label>
            <flux:input wire:model="form.name" required />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>Correo</flux:label>
            <flux:input type="email" wire:model="form.email" required />
            <flux:description>Es el correo con el que entras al panel.</flux:description>
            <flux:error name="form.email" />
        </flux:field>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Guardar cambios</flux:button>
        </div>
    </form>

    <flux:separator />

    <form wire:submit="savePassword" class="space-y-6">
        <flux:heading size="lg">Contraseña</flux:heading>

        <flux:field>
            <flux:label>Contraseña actual</flux:label>
            <flux:input type="password" wire:model="passwordForm.current_password" required />
            <flux:error name="passwordForm.current_password" />
        </flux:field>

        <flux:field>
            <flux:label>Nueva contraseña</flux:label>
            <flux:input type="password" wire:model="passwordForm.password" required />
            <flux:description>Mínimo 8 caracteres.</flux:description>
            <flux:error name="passwordForm.password" />
        </flux:field>

        <flux:field>
            <flux:label>Repite la nueva contraseña</flux:label>
            <flux:input type="password" wire:model="passwordForm.password_confirmation" required />
            <flux:error name="passwordForm.password_confirmation" />
        </flux:field>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Cambiar contraseña</flux:button>
        </div>
    </form>
</div>
