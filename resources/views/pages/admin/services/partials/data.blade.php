<section class="space-y-6">
    <flux:heading size="lg">Campos custom (JSON)</flux:heading>

    <flux:field>
        <flux:label badge="Opcional">Specs / atributos</flux:label>
        <flux:textarea wire:model="form.customFieldsJson" rows="10" class="font-mono text-sm" placeholder='{"power_kva": 100, "fuel": "diesel", "autonomy_h": 14}' />
        <flux:description>
            JSON con los campos específicos del servicio. Cada sitio define su propio esquema (kVA/dB/fuel para generadores; m³/lockable para contenedores; …).
        </flux:description>
        <flux:error name="form.customFieldsJson" />
    </flux:field>
</section>
