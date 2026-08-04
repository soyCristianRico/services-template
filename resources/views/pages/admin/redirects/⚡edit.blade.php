<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Livewire\Forms\Seo\RedirectForm;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public RedirectForm $form;

    public ?Redirect $redirect = null;

    /** Address the tester runs the rule against. */
    public string $testPath = '';

    public function mount(?Redirect $redirect = null): void
    {
        if ($redirect?->exists) {
            $this->redirect = $redirect;
            $this->form->setRedirect($redirect);
            $this->testPath = $redirect->match_type === RedirectMatchType::Exact ? $redirect->source : '';

            return;
        }

        // Arriving from the 404 screen: the address that failed is already known,
        // and retyping it by hand is how a typo turns into a redirect that never
        // fires.
        $source = (string) request()->query('source', '');

        if ($source !== '') {
            $this->form->source = RedirectPath::normalize($source);
            $this->testPath = $this->form->source;
        }
    }

    public function save(): void
    {
        $redirect = $this->form->save();

        // Whatever the address was logged as, it now has an answer.
        NotFoundLog::query()
            ->where('path', $redirect->source)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        session()->flash('status', $this->redirect ? 'Redirección actualizada.' : 'Redirección creada.');

        $this->redirectRoute('admin.redirects.edit', $redirect);
    }

    /**
     * @return array{matches: bool, destination: ?string}
     */
    #[Computed]
    public function testResult(): array
    {
        if ($this->testPath === '' || $this->form->source === '' || ! $this->matchesSource()) {
            return ['matches' => false, 'destination' => null];
        }

        if ($this->form->isGone()) {
            return ['matches' => true, 'destination' => null];
        }

        $destination = $this->form->preview($this->testPath);

        // A rule that matches but produces nothing is broken, not a match: saying
        // "coincide" and showing an empty destination is worse than saying no.
        return ['matches' => $destination !== null, 'destination' => $destination];
    }

    protected function matchesSource(): bool
    {
        $type = $this->form->matchType();
        $path = RedirectPath::normalize($this->testPath);

        if ($type === RedirectMatchType::Regex) {
            return RedirectPath::isValidPattern($this->form->source)
                && @preg_match(RedirectPath::compilePattern($this->form->source), $path) === 1;
        }

        $source = RedirectPath::normalize($this->form->source);

        return $type === RedirectMatchType::Exact
            ? $path === $source
            : $path === $source || str_starts_with($path, $source.'/');
    }

    /**
     * Borra el registro y vuelve al listado, que es de donde se venía.
     *
     * Vive aquí y no en el índice porque borrar desde una fila deja el
     * puntero a un clic del lápiz de al lado; quien borra ya ha abierto
     * la ficha.
     */
    public function delete(): void
    {
        $this->redirect?->delete();

        $this->redirectRoute('admin.redirects.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.redirects.index')">
            {{ $redirect ? 'Editar redirección' : 'Nueva redirección' }}

        @if ($redirect)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar la redirección de {{ $redirect->source }}? Esa dirección volverá a devolver un error 404."
                        icon="trash" variant="danger">
                        Borrar
                    </flux:menu.item>
                </x-admin.record-menu>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Dirección antigua</flux:label>
            <flux:input wire:model.live.debounce.500ms="form.source" required placeholder="/curso-antiguo" class="font-mono" />
            <flux:description>
                La que ya no existe o va a dejar de existir. Puedes pegar la dirección completa
                (<code>https://…/curso-antiguo/</code>): se guarda solo la parte final.
            </flux:description>
            <flux:error name="form.source" />
        </flux:field>

        <flux:field>
            <flux:label>Cómo se compara</flux:label>
            <flux:select wire:model.live="form.match_type">
                @foreach (RedirectMatchType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>{{ $form->matchType()->description() }}</flux:description>
            <flux:error name="form.match_type" />
        </flux:field>

        <flux:field>
            <flux:label>Qué respondemos</flux:label>
            <flux:select wire:model.live="form.status_code">
                @foreach (RedirectStatusCode::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>{{ RedirectStatusCode::from($form->status_code)->description() }}</flux:description>
            <flux:error name="form.status_code" />
        </flux:field>

        @unless ($form->isGone())
            <flux:field>
                <flux:label>Dirección nueva</flux:label>
                <flux:input wire:model.live.debounce.500ms="form.destination" placeholder="/cursos/curso-nuevo" class="font-mono" />
                <flux:description>
                    Empieza por <code>/</code> para una página de esta web, o pon la dirección completa con
                    <code>https://</code> para enviar a otro sitio.
                    @if ($form->matchType() === RedirectMatchType::Regex)
                        Con expresión regular puedes colocar aquí lo capturado usando <code>$1</code>, <code>$2</code>…
                    @endif
                </flux:description>
                <flux:error name="form.destination" />
            </flux:field>
        @endunless

        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 space-y-3">
            <flux:heading size="sm">Probar antes de guardar</flux:heading>
            <flux:input wire:model.live.debounce.500ms="testPath" placeholder="/una-direccion-para-probar" class="font-mono" />

            @if ($testPath !== '')
                @if ($this->testResult['matches'])
                    @if ($form->isGone())
                        <flux:badge color="amber" size="sm">Coincide · respondería «contenido eliminado» (410)</flux:badge>
                    @else
                        <flux:badge color="green" size="sm">
                            Coincide · iría a <span class="font-mono">{{ $this->testResult['destination'] }}</span>
                        </flux:badge>
                    @endif
                @else
                    <flux:badge color="zinc" size="sm">No coincide: esta dirección seguiría igual que ahora</flux:badge>
                @endif
            @else
                <flux:text size="sm" class="text-zinc-500">
                    Escribe una dirección y te decimos si esta regla la atraparía y a dónde la mandaría.
                </flux:text>
            @endif
        </div>

        <flux:separator />

        <flux:field variant="inline">
            <flux:switch wire:model="form.is_active" />
            <flux:label>Activa</flux:label>
            <flux:description>Desactivada se conserva pero no hace nada.</flux:description>
        </flux:field>

        @unless ($form->isGone())
            <flux:field variant="inline">
                <flux:switch wire:model="form.preserve_query" />
                <flux:label>Conservar los parámetros de la dirección</flux:label>
                <flux:description>
                    Arrastra al destino lo que venga después de <code>?</code>. Déjalo activado: es lo que hace que
                    los enlaces de campañas sigan midiéndose bien.
                </flux:description>
            </flux:field>
        @endunless

        <flux:field>
            <flux:label>Nota interna</flux:label>
            <flux:textarea wire:model="form.notes" rows="2" placeholder="Por qué existe esta redirección" />
            <flux:description>No la ve nadie de fuera. Dentro de un año será lo único que explique esta regla.</flux:description>
            <flux:error name="form.notes" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.redirects.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $redirect ? 'Guardar cambios' : 'Crear redirección' }}
            </flux:button>
        </div>
    </form>
</div>
