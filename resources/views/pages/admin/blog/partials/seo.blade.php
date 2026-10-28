<section class="space-y-6">
    <flux:heading size="lg">SEO</flux:heading>

    <flux:field>
        <flux:label badge="Opcional">Meta título</flux:label>
        <flux:input wire:model="form.meta_title" placeholder="Vacío = usa el título" />
        <flux:error name="form.meta_title" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Meta descripción</flux:label>
        <flux:textarea wire:model="form.meta_description" rows="3" placeholder="Vacío = usa la entradilla" />
        <flux:error name="form.meta_description" />
    </flux:field>
</section>
