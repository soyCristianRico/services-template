<div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
    @if ($submitted)
        <div class="space-y-2 text-center" data-test="lead-form-success">
            <flux:heading size="lg">Hemos recibido tu consulta</flux:heading>
            <flux:text class="text-zinc-600">
                Te responderemos lo antes posible.
            </flux:text>
        </div>
    @else
        <flux:heading size="lg">Cuéntanos qué necesitas</flux:heading>
        <flux:text class="mt-2 text-zinc-600">
            Te respondemos sin compromiso.
        </flux:text>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="form.name" required autocomplete="name" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="form.email" required autocomplete="email" />
                <flux:error name="form.email" />
            </flux:field>

            <flux:field>
                <flux:label>Teléfono</flux:label>
                <flux:input type="tel" wire:model="form.phone" autocomplete="tel" />
                <flux:error name="form.phone" />
            </flux:field>

            <flux:field>
                <flux:label>¿Qué necesitas?</flux:label>
                <flux:textarea wire:model="form.message" rows="4" />
                <flux:error name="form.message" />
            </flux:field>

            {{-- Honeypot — hidden from real users via aria + tab + tailwind. Bots tend to fill every input. --}}
            <div aria-hidden="true" class="absolute left-[-9999px] h-0 overflow-hidden">
                <label>
                    Website
                    <input type="text" wire:model="form.website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                <span wire:loading.remove wire:target="save">Enviar consulta</span>
                <span wire:loading wire:target="save">Enviando…</span>
            </flux:button>
        </form>
    @endif
</div>
