<section class="space-y-6">
    <flux:heading size="lg">Página</flux:heading>

    @unless ($page)
        <flux:field>
            <flux:label>Título</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.title" required />
            <flux:error name="form.title" />
        </flux:field>
    @endunless

    <flux:field>
        <flux:label>Slug</flux:label>
        <flux:input wire:model="form.slug" required />
        <flux:description>Sólo minúsculas, números y guiones. Aparece como <code>/{slug}</code>.</flux:description>
        <flux:error name="form.slug" />
    </flux:field>

    <flux:field>
        <flux:label>Activa</flux:label>
        <flux:switch wire:model="form.is_active" />
        <flux:description>Si está inactiva, la URL devuelve 404 y no sale en el sitemap.</flux:description>
    </flux:field>
</section>

<section class="space-y-6">
    <flux:heading size="lg">Contenido</flux:heading>

    <flux:field>
        <flux:label badge="Opcional">Cuerpo</flux:label>
        <x-admin.rich-editor wire:model="form.body" />
        <flux:description>Se pinta tal cual en la página pública, dentro del envoltorio <code>.prose</code>.</flux:description>
        <flux:error name="form.body" />
    </flux:field>
</section>
