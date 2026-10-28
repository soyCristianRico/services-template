<section class="space-y-6">
    <flux:heading size="lg">Contenido</flux:heading>

    @unless ($post)
        <flux:field>
            <flux:label>Título</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.title" required />
            <flux:error name="form.title" />
        </flux:field>
    @endunless

    <flux:field>
        <flux:label>Slug</flux:label>
        <flux:input wire:model="form.slug" required />
        <flux:description>URL: /blog/{slug}. Sólo minúsculas, números y guiones.</flux:description>
        <flux:error name="form.slug" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Entradilla</flux:label>
        <flux:textarea wire:model="form.excerpt" rows="2" placeholder="Una entradilla corta (320 caracteres)" />
        <flux:error name="form.excerpt" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Cuerpo</flux:label>
        <x-admin.rich-editor wire:model="form.body" />
        <flux:error name="form.body" />
    </flux:field>
</section>
