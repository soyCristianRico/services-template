<section class="space-y-6">
    <flux:heading size="lg">Servicio</flux:heading>

    @unless ($service)
        <flux:field>
            <flux:label>Nombre</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.name" required />
            <flux:error name="form.name" />
        </flux:field>
    @endunless

    <flux:field>
        <flux:label>Slug</flux:label>
        <flux:input wire:model="form.slug" required />
        <flux:description>Sólo minúsculas, números y guiones.</flux:description>
        <flux:error name="form.slug" />
    </flux:field>

    <flux:field>
        <flux:label>Categoría</flux:label>
        <flux:select variant="listbox" searchable wire:model="form.category_id" placeholder="Elige la categoría">
            @foreach ($this->categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:error name="form.category_id" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Categorías adicionales</flux:label>
        <flux:select variant="listbox" searchable multiple wire:model="form.additional_category_ids" placeholder="Ninguna">
            @foreach ($this->categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:description>El servicio también aparecerá en estas categorías, además de la principal.</flux:description>
        <flux:error name="form.additional_category_ids" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Descripción corta</flux:label>
        <flux:input wire:model="form.short_description" placeholder="Una línea (255 caracteres)" />
        <flux:error name="form.short_description" />
    </flux:field>

    <flux:field>
        <flux:label badge="Opcional">Descripción larga</flux:label>
        <flux:textarea wire:model="form.description" rows="6" />
        <flux:error name="form.description" />
    </flux:field>
</section>

<section class="space-y-6">
    <flux:heading size="lg">Publicación</flux:heading>

    <flux:field>
        <flux:label>Activo</flux:label>
        <div>
            <flux:switch wire:model="form.is_active" />
        </div>
        <flux:description>Desactivado, deja de salir en la web pero se conserva aquí.</flux:description>
    </flux:field>

    <flux:field>
        <flux:label>Posición (orden)</flux:label>
        <flux:input type="number" wire:model="form.position" min="0" class="max-w-40" />
        <flux:description>Más bajo aparece antes en el listado.</flux:description>
        <flux:error name="form.position" />
    </flux:field>
</section>
