<section class="space-y-6">
    <flux:heading size="lg">Publicación</flux:heading>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:field>
            <flux:label badge="Opcional">Publicado el</flux:label>
            <flux:input type="datetime-local" wire:model="form.published_at" />
            <flux:description>Vacío = borrador. Futuro = programado. Pasado = público.</flux:description>
            <flux:error name="form.published_at" />
        </flux:field>

        <flux:field>
            <flux:label>Activo</flux:label>
            <flux:switch wire:model="form.is_active" />
            <flux:description>Si está inactivo, no sale aunque tenga fecha.</flux:description>
        </flux:field>
    </div>
</section>

<section class="space-y-6">
    <flux:heading size="lg">Autoría y etiquetas</flux:heading>

    <div class="grid gap-6 md:grid-cols-2">
        <flux:field>
            <flux:label badge="Opcional">Autor</flux:label>
            <flux:input wire:model="form.author_name" placeholder="Nombre que aparece bajo el título" />
            <flux:error name="form.author_name" />
        </flux:field>

        <flux:field>
            <flux:label badge="Opcional">Etiquetas</flux:label>
            <flux:input wire:model="form.tagsCsv" placeholder="seo, generadores, comparativa" />
            <flux:description>Separadas por comas.</flux:description>
            <flux:error name="form.tagsCsv" />
        </flux:field>
    </div>
</section>
